<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );
        $this->app->singleton(
            \Filament\Http\Responses\Auth\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );
        $this->app->singleton(
            \Filament\Http\Responses\Auth\Contracts\RegistrationResponse::class,
            \App\Http\Responses\RegistrationResponse::class
        );

        // Bind RAG bridge interface to the real Python implementation.
        // Tests can swap this with app()->instance(RagBridgeService::class, $fake).
        $this->app->singleton(
            \App\Services\AI\RagBridgeService::class,
            \App\Services\AI\PythonRagBridgeService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict();
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\DepositRequest::observe(\App\Observers\DepositRequestObserver::class);

        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, $url) {
            \Illuminate\Support\Facades\Log::info('AppServiceProvider VerifyEmail callback triggered for: ' . $notifiable->email);
            
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Verifica tu correo electrónico - Soluciones Edgar')
                ->view('emails.auth.verify', ['url' => $url, 'user' => $notifiable]);
        });

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, $token) {
            return (new \App\Notifications\ResetPasswordNotification($token))->toMail($notifiable);
        });

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key')
                )
            );
        });
    }
}
