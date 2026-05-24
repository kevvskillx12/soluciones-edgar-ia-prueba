<?php

namespace App\Observers;

use App\Jobs\ProcessExternalOrderJob;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "creating" event.
     */
    public function creating(Order $order): void
    {
        if ($order->service) {
            $order->service_price_snapshot = $order->service->price;
            $order->service_cost_snapshot = $order->service->cost ?? 0;

            if (auth()->check() && auth()->user()->is_admin) {
                $order->price_at_purchase = 0;
            } else {
                $order->price_at_purchase = $order->service->price;
            }
        }
    }

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        if ($order->price_at_purchase > 0) {
            try {
                $order->user->subtractBalance(
                    $order->price_at_purchase, 
                    "Pago de servicio: {$order->service->name} (Pedido #{$order->id})", 
                    $order
                );
            } catch (\Exception $e) {
                throw $e;
            }
        }

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id() ?? $order->user_id,
            'event' => 'order_created',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'description' => "Pedido #{$order->id} creado. Costo: \${$order->price_at_purchase}",
            'properties' => $order->toArray(),
            'ip_address' => request()->ip(),
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Nuevo Pedido Recibido')
            ->body("Usuario: {$order->user->name}. Servicio: {$order->service->name}")
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Ver Pedido')
                    ->url('/admin/orders/' . $order->id . '/edit')
            ])
            ->sendToDatabase(\App\Models\User::where('is_admin', true)->get());

        $admins = \App\Models\User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\AdminOrderCreated($order));
        }

        // Despachar procesamiento automático por API externa si aplica
        try {
            $service = $order->service;
            if ($service && $service->automation_type === 'automatic') {
                Log::info("[OrderObserver] Order #{$order->id} con servicio automático. Despachando ProcessExternalOrderJob.");
                $order->updateQuietly(['api_status' => 'queued']);
                ProcessExternalOrderJob::dispatch($order);
            }
        } catch (\Throwable $e) {
            // No bloquear la creación del pedido si el despacho del Job falla
            Log::error("[OrderObserver] Error al despachar Job para Order #{$order->id}: " . $e->getMessage());
        }
    }

     public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'rejected') {
            $refundAmount = $order->price_at_purchase ?? $order->service->price;
            
            if ($refundAmount > 0) {
                try {
                     $order->user->credit(
                        $refundAmount, 
                        "Reembolso por pedido #{$order->id} (Servicio no disponible/Rechazado)",
                        $order
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Reembolso Procesado')
                        ->body("Se han devuelto \${$refundAmount} al usuario {$order->user->name}.")
                        ->success()
                        ->sendToDatabase(\App\Models\User::where('is_admin', true)->get());

                    \Filament\Notifications\Notification::make()
                        ->title('Pedido Rechazado y Reembolsado')
                        ->body("Tu pedido de {$order->service->name} no pudo ser completado. Hemos reembolsado \${$refundAmount} a tu cuenta. Motivo: " . ($order->admin_notes ?? 'No especificado'))
                        ->danger()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('Ver Detalles')
                                ->url('/app/movements')
                        ])
                        ->sendToDatabase($order->user);

                } catch (\Exception $e) {
                    \Log::error("Error processing refund for order {$order->id}: " . $e->getMessage());
                }
            } else {
                 \Filament\Notifications\Notification::make()
                    ->title('Pedido Rechazado')
                    ->body("Tu pedido de {$order->service->name} ha sido rechazado. " . ($order->admin_notes ? "Motivo: {$order->admin_notes}" : ""))
                    ->danger()
                    ->sendToDatabase($order->user);
            }
        }

        if ($order->wasChanged('status') && $order->status === 'processing') {
            \Filament\Notifications\Notification::make()
                ->title('Tu pedido está en proceso')
                ->body("Estamos trabajando en tu solicitud de {$order->service->name}. Te notificaremos cuando esté lista.")
                ->info()
                ->sendToDatabase($order->user);
        }

        if ($order->wasChanged('status') && $order->status === 'completed') {
            \Filament\Notifications\Notification::make()
                ->title('¡Trámite Listo!')
                ->body("Tu trámite de {$order->service->name} ha sido completado. Puedes descargar tu documento en la sección 'Mis Trámites'.")
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('download')
                        ->label('Ver Trámite')
                        ->url('/app/orders')
                ])
                ->sendToDatabase($order->user);

            try {
                \Illuminate\Support\Facades\Mail::to($order->user->email)
                    ->send(new \App\Mail\OrderCompleted($order));
                
                \App\Models\ActivityLog::create([
                    'user_id' => $order->user_id, 
                    'event' => 'email_sent',
                    'subject_type' => Order::class,
                    'subject_id' => $order->id,
                    'description' => "Email de resultado enviado a {$order->user->email}",
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::error("Error enviando correo de pedido completado: " . $e->getMessage());
                \App\Models\ActivityLog::create([
                     'user_id' => auth()->id(),
                     'event' => 'email_failed',
                     'subject_type' => Order::class,
                     'subject_id' => $order->id,
                     'description' => "Fallo el envío de email: " . $e->getMessage(),
                     'ip_address' => request()->ip(),
                ]);
            }
        }
        
        if ($order->wasChanged('status')) {
             \App\Models\ActivityLog::create([
                'user_id' => auth()->id() ?? $order->user_id,
                'event' => 'status_updated',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'description' => "Estado cambiado de '{$order->getOriginal('status')}' a '{$order->status}'",
                'properties' => ['old' => $order->getOriginal('status'), 'new' => $order->status],
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        if (($order->price_at_purchase > 0) && $order->status !== 'rejected') {
             try {
                 $refundAmount = $order->price_at_purchase;
                 
                 $order->user->credit(
                    $refundAmount, 
                    "Reembolso por pedido eliminado #{$order->id}",
                    null 
                );
                
                \Filament\Notifications\Notification::make()
                    ->title('Pedido Eliminado y Reembolsado')
                    ->body("Tu pedido de {$order->service->name} ha sido eliminado por un administrador. Se han devuelto \${$refundAmount} a tu cuenta.")
                    ->warning() 
                    ->sendToDatabase($order->user);

            } catch (\Exception $e) {
                \Log::error("Error refunding deleted order {$order->id}: " . $e->getMessage());
            }
        } else {
             \Filament\Notifications\Notification::make()
                ->title('Pedido Eliminado')
                ->body("Tu pedido de {$order->service->name} ha sido eliminado por el administrador.")
                ->danger()
                ->sendToDatabase($order->user);
        }
    }

    public function restored(Order $order): void
    {
        //
    }

    
    public function forceDeleted(Order $order): void
    {
    }
}
