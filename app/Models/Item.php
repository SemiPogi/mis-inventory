<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'category',
        'brand',
        'model_number',
        'serial_number',
        'unit',
        'total_qty_received',
        'current_qty',
        'created_by',
        'department_id',
        'expiry_date',
        'min_stock_qty',
        'warranty_expiry_date',
        'warranty_provider',
        'warranty_reference_no',
        'warranty_notes',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'          => 'date',
            'warranty_expiry_date' => 'date',
            'current_qty'          => 'integer',
            'min_stock_qty'        => 'integer',
            'total_qty_received'   => 'integer',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ItemLog::class)->latest('created_at')->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiry_date !== null
            && ! $this->expiry_date->isPast()
            && $this->expiry_date->lte(now()->addDays(30));
    }

    public function isBelowMinStock(): bool
    {
        return $this->min_stock_qty > 0 && $this->current_qty <= $this->min_stock_qty;
    }

    /** Expiry badge: 'expired' | 'soon' | 'ok' | null */
    public function expiryStatus(): ?string
    {
        if ($this->expiry_date === null) return null;
        if ($this->isExpired())         return 'expired';
        if ($this->isExpiringSoon())    return 'soon';
        return 'ok';
    }

    /**
     * Warranty status badge: 'expired' | 'expiring' | 'expiring-soon' | 'active' | null
     *
     * expired       = past expiry
     * expiring      = within 30 days  (red)
     * expiring-soon = 31–90 days      (amber)
     * active        = more than 90 days (green)
     */
    public function warrantyStatus(): ?string
    {
        if (! $this->warranty_expiry_date) return null;
        $days = now()->diffInDays($this->warranty_expiry_date, false);
        if ($days < 0)   return 'expired';
        if ($days <= 30) return 'expiring';
        if ($days <= 90) return 'expiring-soon';
        return 'active';
    }
}