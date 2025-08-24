<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Enviar notificación personalizada de reset de contraseña
     */
public function sendPasswordResetNotification($token)
{
    $url = config('app.frontend_url')
        . '/new-password?token=' . $token
        . '&email=' . urlencode($this->email);

    $this->notify(new \App\Notifications\CustomResetPasswordNotification($url));
}

}
