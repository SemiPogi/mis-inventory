<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'department_id',
        'is_head',
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
            'is_head'           => 'boolean',
        ];
    }

    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isStaff(): bool      { return $this->role === 'staff'; }
    public function isAccounting(): bool { return $this->role === 'accounting'; }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isHead(): bool
    {
        return (bool) $this->is_head;
    }

    /**
     * Returns the department_id to use as a WHERE filter, or null if the user
     * can see all departments (admin / accounting).
     */
    public function departmentScope(): ?int
    {
        if (in_array($this->role, ['admin', 'accounting'])) {
            return null;
        }
        return $this->department_id;
    }

    /** All authenticated users can access reports — scoped to their dept. */
    public function canAccessReports(): bool
    {
        return true;
    }

    /** Admin and accounting can see cross-department reports. */
    public function canAccessHospitalWideReports(): bool
    {
        return in_array($this->role, ['admin', 'accounting']);
    }

    public function canManageUsers(): bool   { return $this->role === 'admin'; }
    public function canCreateVoucher(): bool { return in_array($this->role, ['admin', 'staff']); }
    public function canSettleVoucher(): bool { return in_array($this->role, ['admin', 'accounting']); }
}
