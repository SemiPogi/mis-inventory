<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isStaff(): bool      { return $this->role === 'staff'; }
    public function isAccounting(): bool { return $this->role === 'accounting'; }

    public function canAccessReports(): bool { return in_array($this->role, ['admin', 'accounting']); }
    public function canManageUsers(): bool   { return $this->role === 'admin'; }
    public function canCreateVoucher(): bool { return in_array($this->role, ['admin', 'staff']); }
    public function canSettleVoucher(): bool { return in_array($this->role, ['admin', 'accounting']); }
}
