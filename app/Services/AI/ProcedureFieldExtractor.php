<?php

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ProcedureFieldExtractor
{
    /**
     * @return array{
     *     found: bool,
     *     field_name: ?string,
     *     value: mixed,
     *     confidence: string,
     *     reason: string,
     *     needs_clarification: bool,
     *     fields: array<string, mixed>
     * }
     */
    public function extract(
        string $message,
        ?string $currentField,
        array $currentDefinition,
        array $state,
        array $fields
    ): array {
        $detected = [];
        $reasons = [];

        foreach ($fields as $field) {
            $result = $this->extractField($message, $field, ($field['name'] ?? null) === $currentField);
            if ($result['found']) {
                $detected[$field['name']] = $result['value'];
                $reasons[$field['name']] = $result['reason'];
            }
        }

        $primaryName = array_key_exists((string) $currentField, $detected)
            ? $currentField
            : array_key_first($detected);

        return [
            'found' => $primaryName !== null,
            'field_name' => $primaryName,
            'value' => $primaryName !== null ? $detected[$primaryName] : null,
            'confidence' => $primaryName !== null ? 'high' : 'none',
            'reason' => $primaryName !== null ? $reasons[$primaryName] : 'no_valid_field_value',
            'needs_clarification' => $primaryName === null && $currentField !== null,
            'fields' => $detected,
        ];
    }

    private function extractField(string $message, array $field, bool $isCurrent): array
    {
        $name = (string) ($field['name'] ?? '');
        $type = $this->normalize((string) ($field['type'] ?? 'text'));
        $semanticType = $this->semanticType($field);

        if (in_array($type, ['file', 'upload', 'document', 'documento', 'archivo'], true)) {
            return $this->notFound('manual_upload_required');
        }

        if ($semanticType === 'email') {
            return preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', $message, $match)
                ? $this->validated($match[0], $field, 'email_pattern')
                : $this->notFound();
        }

        if ($semanticType === 'curp') {
            return preg_match('/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d\b/iu', $message, $match)
                ? $this->validated(mb_strtoupper($match[0]), $field, 'curp_pattern')
                : $this->notFound();
        }

        if ($semanticType === 'rfc') {
            return preg_match('/\b[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}\b/iu', $message, $match)
                ? $this->validated(mb_strtoupper($match[0]), $field, 'rfc_pattern')
                : $this->notFound();
        }

        if ($semanticType === 'nss') {
            return preg_match('/(?<!\d)(\d[\d\s-]{9,13}\d)(?!\d)/u', $message, $match)
                ? $this->validated(preg_replace('/\D/u', '', $match[1]), $field, 'nss_pattern')
                : $this->notFound();
        }

        if ($semanticType === 'phone') {
            if (preg_match('/(?<!\d)(?:\+?52[\s-]?)?(\d{2,3}(?:[\s-]?\d){7,8})(?!\d)/u', $message, $match)) {
                $digits = preg_replace('/\D/u', '', $match[1]);
                return strlen($digits) === 10
                    ? $this->validated($digits, $field, 'phone_pattern')
                    : $this->notFound();
            }

            return $this->notFound();
        }

        if ($semanticType === 'date') {
            $date = $this->extractDate($message);
            return $date ? $this->validated($date, $field, 'date_pattern') : $this->notFound();
        }

        if ($semanticType === 'number') {
            if ($this->hasAlias($message, $field) && preg_match('/[-+]?\d+(?:[.,]\d+)?/u', $message, $match)) {
                return $this->validated(str_replace(',', '.', $match[0]), $field, 'labeled_number');
            }
            if ($isCurrent && preg_match('/^\s*[-+]?\d+(?:[.,]\d+)?\s*$/u', $message, $match)) {
                return $this->validated(str_replace(',', '.', trim($match[0])), $field, 'direct_number');
            }

            return $this->notFound();
        }

        $options = $this->options($field);
        if ($options !== []) {
            foreach ($options as $option) {
                if ($this->containsNormalized($message, (string) $option)) {
                    return $this->validated($option, $field, 'configured_option');
                }
            }

            return $this->notFound('unknown_option');
        }

        $candidate = $this->extractLabeledText($message, $field);
        if ($candidate !== null) {
            return $this->validated($candidate, $field, 'labeled_text');
        }

        if ($isCurrent && $this->isDirectValue($message)) {
            return $this->validated($this->cleanValue($message), $field, 'direct_value');
        }

        return $this->notFound();
    }

    private function extractLabeledText(string $message, array $field): ?string
    {
        $aliases = $this->aliases($field);
        $aliasPattern = implode('|', array_map(
            fn (string $alias) => preg_quote($alias, '/'),
            array_filter($aliases, fn (string $alias) => $alias !== '')
        ));

        if ($aliasPattern === '') {
            return null;
        }

        $patterns = [
            '/(?:^|[,;]\s*|\b)(?:mi\s+|el\s+|la\s+)?(?:' . $aliasPattern . ')\s*(?:correct[oa]\s+)?(?:es|:|=|como|a)?\s+(.+?)(?=\s*(?:,|;|\by\s+(?:mi\s+|el\s+|la\s+)?\w+\s*(?:es|:|=))|$)/iu',
        ];

        if ($this->semanticType($field) === 'name') {
            $patterns[] = '/\bsoy\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){1,3})(?=\s*[,;]|\s+y\s+mi\b|$)/iu';
            $patterns[] = '/\bes\s+para\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){1,3})(?=\s*[,;]|$)/iu';
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $match)) {
                $value = $this->cleanValue($match[1]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function validated(mixed $value, array $field, string $reason): array
    {
        $value = is_string($value) ? $this->cleanValue($value) : $value;

        if ($value === '' || !$this->passesValidation($value, $field)) {
            return $this->notFound('validation_failed');
        }

        return ['found' => true, 'value' => $value, 'reason' => $reason];
    }

    private function passesValidation(mixed $value, array $field): bool
    {
        $string = (string) $value;
        $regex = $field['regex'] ?? $field['validation']['regex'] ?? null;
        if ($regex && @preg_match($regex, $string) !== 1) {
            return false;
        }

        $semanticType = $this->semanticType($field);

        return match ($semanticType) {
            'curp' => preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/u', mb_strtoupper($string)) === 1,
            'rfc' => preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', mb_strtoupper($string)) === 1,
            'nss' => preg_match('/^\d{11}$/', $string) === 1,
            'email' => filter_var($string, FILTER_VALIDATE_EMAIL) !== false,
            'phone' => preg_match('/^\d{10}$/', $string) === 1,
            default => !$this->isGenericIntent($string),
        };
    }

    private function semanticType(array $field): string
    {
        $haystack = $this->normalize(implode(' ', [
            $field['name'] ?? '',
            $field['label'] ?? '',
            $field['type'] ?? '',
        ]));

        return match (true) {
            str_contains($haystack, 'curp') => 'curp',
            str_contains($haystack, 'rfc') => 'rfc',
            str_contains($haystack, 'nss'), str_contains($haystack, 'seguro social') => 'nss',
            str_contains($haystack, 'correo'), str_contains($haystack, 'email'), str_contains($haystack, 'mail') => 'email',
            str_contains($haystack, 'telefono'), str_contains($haystack, 'celular'), str_contains($haystack, 'whatsapp') => 'phone',
            str_contains($haystack, 'fecha'), str_contains($haystack, 'nacimiento') => 'date',
            str_contains($haystack, 'nombre'), str_contains($haystack, 'persona'), str_contains($haystack, 'cliente') => 'name',
            in_array($this->normalize((string) ($field['type'] ?? '')), ['number', 'numeric', 'integer', 'decimal'], true) => 'number',
            default => 'text',
        };
    }

    private function aliases(array $field): array
    {
        $name = $this->normalize(str_replace('_', ' ', (string) ($field['name'] ?? '')));
        $label = $this->normalize(preg_replace('/\([^)]*\)/u', '', (string) ($field['label'] ?? '')));
        $aliases = [$name, $label];

        $map = [
            'curp' => ['curp', 'clave curp'],
            'rfc' => ['rfc'],
            'nss' => ['nss', 'seguro social', 'numero de seguro social'],
            'phone' => ['telefono', 'celular', 'numero', 'whatsapp'],
            'email' => ['correo', 'correo electronico', 'email', 'mail'],
            'name' => ['nombre', 'nombre completo', 'persona', 'cliente', 'interesado'],
            'date' => ['fecha de nacimiento', 'nacimiento', 'naci', 'nacio'],
        ];

        $aliases = array_merge($aliases, $map[$this->semanticType($field)] ?? []);
        $configured = $field['aliases'] ?? $field['synonyms'] ?? [];
        if (is_string($configured)) {
            $configured = array_map('trim', explode(',', $configured));
        }

        return array_values(array_unique(array_filter(array_merge($aliases, (array) $configured))));
    }

    private function options(array $field): array
    {
        $options = $field['options'] ?? $field['validation']['options'] ?? [];
        if (!is_array($options)) {
            return [];
        }

        return array_is_list($options) ? $options : array_values($options);
    }

    private function extractDate(string $message): ?string
    {
        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $message, $match)) {
            return $this->validDate((int) $match[1], (int) $match[2], (int) $match[3]);
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $message, $match)) {
            return $this->validDate((int) $match[3], (int) $match[2], (int) $match[1]);
        }

        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];
        if (preg_match('/\b(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})\b/iu', $message, $match)) {
            $month = $months[$this->normalize($match[2])] ?? null;
            return $month ? $this->validDate((int) $match[3], $month, (int) $match[1]) : null;
        }

        return null;
    }

    private function validDate(int $year, int $month, int $day): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }

    private function isDirectValue(string $message): bool
    {
        $normalized = $this->normalize($message);
        if ($this->isGenericIntent($normalized) || str_word_count($normalized) > 8) {
            return false;
        }

        return !preg_match('/\b(quiero|necesito|tramite|tramitar|solicitud|ayuda|cancelar|cambiar)\b/u', $normalized);
    }

    private function isGenericIntent(string $value): bool
    {
        $normalized = $this->normalize($value);

        return $normalized === ''
            || preg_match('/^(hola|ayuda|si|no|continua|quiero|necesito|hacer un tramite|tramitar una?)\b/u', $normalized) === 1;
    }

    private function hasAlias(string $message, array $field): bool
    {
        foreach ($this->aliases($field) as $alias) {
            if ($this->containsNormalized($message, $alias)) {
                return true;
            }
        }

        return false;
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        return str_contains($this->normalize($haystack), $this->normalize($needle));
    }

    private function cleanValue(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B,.;:¿?¡!");
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($value), 'UTF-8')));
    }

    private function notFound(string $reason = 'no_match'): array
    {
        return ['found' => false, 'value' => null, 'reason' => $reason];
    }
}
