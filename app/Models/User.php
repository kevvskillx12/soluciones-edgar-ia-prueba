<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmail);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string 
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'is_admin',
        'company_name',
        'phone',
        'account_type',
        'is_verified_company',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, 
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }


    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function depositRequests()
    {
        return $this->hasMany(DepositRequest::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function credit(float $amount, string $description, $reference = null)
    {
        return \DB::transaction(function () use ($amount, $description, $reference) {
            $this->increment('balance', $amount);

            return $this->transactions()->create([
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
                'type' => ($reference instanceof \App\Models\DepositRequest) ? 'deposit' : 'refund',
            ]);
        });
    }
    
    public function addBalance(float $amount, string $type, string $description, $reference = null)
    {
         return \DB::transaction(function () use ($amount, $type, $description, $reference) {
            $this->increment('balance', $amount);

            return $this->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);
        });
    }

    public function subtractBalance(float $amount, string $description, $reference = null)
    {
         return \DB::transaction(function () use ($amount, $description, $reference) {
            if (! $this->is_admin && $this->balance < $amount) {
                throw new \Exception('Saldo insuficiente');
            }
            $this->decrement('balance', $amount);

            return $this->transactions()->create([
                'type' => 'purchase',
                'amount' => -$amount, 'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin;
        }

        return true;
    }
}
