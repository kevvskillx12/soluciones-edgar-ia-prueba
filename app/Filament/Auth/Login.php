<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (! auth()->check()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                new \Illuminate\Http\RedirectResponse('/admin/direct-login')
            );
        }

        parent::mount();
    }

    protected function getRedirectUrl(): string
    {
        $user = auth()->user();
        if ($user && $user->is_admin) {
            return '/admin';
        }
        
        return '/app';
    }
}
