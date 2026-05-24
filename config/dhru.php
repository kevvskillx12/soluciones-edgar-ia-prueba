<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dhru API - Integración con proveedor externo
    |--------------------------------------------------------------------------
    |
    | Configuración para la conexión con la API de Dhru.
    | Si 'enabled' es false, el sistema funcionará en modo simulación
    | sin hacer llamadas HTTP reales al proveedor.
    |
    */

    'enabled' => env('DHRU_API_ENABLED', false),

    'api_url' => env('DHRU_API_URL'),

    'username' => env('DHRU_API_USERNAME'),

    'access_key' => env('DHRU_API_ACCESS_KEY'),

    'request_format' => env('DHRU_API_REQUEST_FORMAT', 'JSON'),

    'timeout' => env('DHRU_API_TIMEOUT', 120),

    'provider_name' => env('DHRU_API_PROVIDER_NAME', 'dhru'),

];
