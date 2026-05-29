<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PettyCashVoucher extends Model
{
    protected $fillable = [
        'voucher_number', 'or_number', 'store_name', 'releasing_officer',
        'requested_amount', 'transport_fee', 'total_amount', 'change_amount',
        'date_purchased', 'status', 'acknowledged_by', 'acknowledged_at',
        'change_returned_by', 'change_returned_at', 'created_by', 'remarks',
        'department_id',
    ];

    protected $casts = [
        'date_purchased'     => 'date',
        'acknowledged_at'    => 'datetime',
        'change_returned_at' => 'datetime',
        'requested_amount'   => 'decimal:2',
        'transport_fee'      => 'decimal:2',
        'total_amount'       => 'decimal:2',
        'change_amount'      => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PettyCashItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function changeReturnedBy()
    {
        return $this->belongsTo(User::class, 'change_returned_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Generate next voucher number like PCV-2026-001.
     * Uses a DB lock to prevent duplicates under concurrent requests.
     */
    public static function generateVoucherNumber(): string
    {
        return DB::transaction(function () {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('PCV-%d-%03d', $year, $count + 1);
        });
    }
}
