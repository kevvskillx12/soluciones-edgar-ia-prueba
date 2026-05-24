<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\Automation\ExternalOrderAutomationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\FileUpload;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Gestión de Trámites';
    protected static ?string $modelLabel = 'Trámite';
    protected static ?string $pluralModelLabel = 'Gestión de Trámites';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Pedido')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->relationship('user', 'name')
                                    ->label('Usuario')
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Select::make('service_id')
                                    ->relationship('service', 'name')
                                    ->label('Servicio')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('input_data', [])),
                            ]),
                        
                        Forms\Components\Group::make()
                            ->schema(function (Forms\Get $get) {
                                $serviceId = $get('service_id');
                                if (! $serviceId) {
                                    return [];
                                }
                                $service = \App\Models\Service::find($serviceId);
                                if (! $service || ! $service->form_schema) {
                                     return [
                                        Forms\Components\TextInput::make('input_data.text')
                                            ->label('Detalles adicionales')
                                            ->required(),
                                    ];
                                }
                                
                                return collect($service->form_schema)->map(function ($field) {
                                    $input = Forms\Components\TextInput::make("input_data.{$field['name']}")
                                        ->label($field['label'])
                                        ->required($field['required'] ?? false);
        
                                    if (isset($field['regex'])) {
                                        $input->regex($field['regex']);
                                    }
        
                                    return $input;
                                })->toArray();
                            }),
                    ]),

                Forms\Components\Section::make('Estado y Entrega')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'processing' => 'En Proceso',
                                'completed' => 'Completado',
                                'rejected' => 'Rechazado',
                            ])
                            ->required()
                            ->default('pending')
                            ->native(false),
                        Forms\Components\FileUpload::make('result_file_path')
                            ->label('Archivo Resultado (PDF)')
                            ->disk('s3')
                            ->directory('order-results')
                            ->acceptedFileTypes(['application/pdf'])
                            ->downloadable()
                            ->openable(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notas del Admin')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Información de API Externa')
                    ->description('Datos del procesamiento por proveedor externo. Estos campos son de solo lectura.')
                    ->icon('heroicon-o-server-stack')
                    ->collapsible()
                    ->collapsed(fn (?Order $record): bool => !($record?->processed_by_api ?? false))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('api_status')
                                    ->label('Estado API')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Sin procesar'),
                                Forms\Components\TextInput::make('external_provider')
                                    ->label('Proveedor Externo')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('—'),
                                Forms\Components\TextInput::make('external_order_id')
                                    ->label('ID Orden Externa')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('—'),
                            ]),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('api_processed_at')
                                    ->label('Fecha de Procesamiento API')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('processed_by_api')
                                    ->label('Procesado por API')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state) => $state ? '✅ Sí' : '❌ No'),
                            ]),
                        Forms\Components\Textarea::make('api_report')
                            ->label('Reporte de API')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('Sin reporte disponible.'),
                        Forms\Components\Textarea::make('api_error_message')
                            ->label('Mensaje de Error API')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Sin errores.')
                            ->visible(fn (?Order $record): bool => !empty($record?->api_error_message)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servicio')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_at_purchase')
                    ->label('Costo')
                    ->money('MXN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'completed' => 'success',
                        'rejected' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'processing' => 'En Proceso',
                        'completed' => 'Completado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('processed_by_api')
                    ->label('API')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Order $record): string => $record->processed_by_api
                        ? 'Procesado por API externa'
                        : 'No procesado por API')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('api_status')
                    ->label('Estado API')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent' => 'info',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'manual_review' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sent' => 'Enviado',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                        'manual_review' => 'Revisión manual',
                        null => '—',
                        default => $state,
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('external_provider')
                    ->label('Proveedor')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('external_order_id')
                    ->label('Orden Externa')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                Tables\Columns\TextColumn::make('api_processed_at')
                    ->label('Fecha API')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(\App\Filament\Exports\AdminOrderExporter::class)
                    ->label('Exportar Reporte')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->formats([
                        \Filament\Actions\Exports\Enums\ExportFormat::Xlsx,
                        \Filament\Actions\Exports\Enums\ExportFormat::Csv,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'processing' => 'En Proceso',
                        'completed' => 'Completado',
                        'rejected' => 'Rechazado',
                    ]),
                Tables\Filters\SelectFilter::make('api_status')
                    ->label('Estado API')
                    ->options([
                        'sent' => 'Enviado',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                        'manual_review' => 'Revisión manual',
                    ]),
                Tables\Filters\TernaryFilter::make('processed_by_api')
                    ->label('Procesado por API')
                    ->placeholder('Todos')
                    ->trueLabel('Sí')
                    ->falseLabel('No'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Order $record) => route('orders.download', ['order' => $record->id]))
                    ->visible(fn (Order $record) => $record->status === 'completed' && $record->result_file_path),
                Tables\Actions\Action::make('upload_result')
                    ->label('Subir Resultado')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->form([
                        FileUpload::make('result_file_path')
                            ->label('Archivo PDF')
                            ->required()
                            ->disk('s3')
                            ->directory('order-results')
                            ->acceptedFileTypes(['application/pdf']),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notas'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $record->update([
                            'result_file_path' => $data['result_file_path'],
                            'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                            'status' => 'completed',
                        ]);

                            Notification::make()
                                ->title('Trámite completado')
                                ->body('El archivo se guardó y el correo se enviará automáticamente.')
                                ->success()
                                ->send();
                    })
                    ->visible(fn (Order $record) => $record->status !== 'completed'),
                Tables\Actions\Action::make('process_api')
                    ->label('Procesar por API externa')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Procesar por API externa')
                    ->modalDescription('¿Estás seguro de enviar este pedido al proveedor externo? Si la API no está habilitada, se ejecutará en modo simulación.')
                    ->modalSubmitActionLabel('Sí, procesar')
                    ->action(function (Order $record): void {
                        try {
                            $automationService = app(ExternalOrderAutomationService::class);
                            $result = $automationService->process($record);

                            if ($result['success'] ?? false) {
                                Notification::make()
                                    ->title('Procesamiento exitoso')
                                    ->body($result['message'] ?? 'El pedido fue procesado correctamente.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Error en el procesamiento')
                                    ->body($result['message'] ?? 'Ocurrió un error al procesar el pedido.')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error inesperado')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Order $record): bool =>
                        $record->status !== 'completed'
                        && $record->api_status !== 'completed'
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
