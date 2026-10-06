<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdminUtama(): bool
    {
        return $this->role === UserRole::AdminUtama;
    }

    public function isSubAdmin(): bool
    {
        return $this->role === UserRole::SubAdmin;
    }

    /**
     * Admin Utama maupun Sub Admin — keduanya "Admin" di UI.
     */
    public function isAdmin(): bool
    {
        return $this->isAdminUtama() || $this->isSubAdmin();
    }

    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }
}
