<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'magic_token',
        'magic_token_expires_at',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'magic_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'magic_token_expires_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
