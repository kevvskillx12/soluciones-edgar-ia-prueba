<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    use Queueable;

    public function __construct($token)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable)
    {
        $routeName = $notifiable->is_admin 
            ? 'filament.admin.auth.password-reset.reset' 
            : 'filament.dashboard.auth.password-reset.reset';
            
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            $routeName,
            \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.passwords.users.expire', 60)),
            [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]
        );

        \Illuminate\Support\Facades\Log::info('Enviando correo de restablecimiento a: ' . $notifiable->email . ' con URL: ' . $url);

        return (new MailMessage)
            ->subject('Restablecer Contraseña - Soluciones Edgar')
            ->view('emails.auth.reset', [
                'notifiable' => $notifiable,
                'url' => $url,
            ]);
    }
}
