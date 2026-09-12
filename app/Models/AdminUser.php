<?php

namespace App\Models;

use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Admini žive u vlastitoj tablici — nema `users` tablice ni `is_admin` flaga,
 * svaki redak u `admin_users` je administrator.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password'])]
class AdminUser extends Authenticatable
{
    /** @use HasFactory<AdminUserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admin_users';

    /** Tablica nema remember_token. */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
