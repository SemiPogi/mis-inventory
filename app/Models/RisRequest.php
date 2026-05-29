<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RisRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ris_number',
        'requesting_dept_id',
        'status',
        'purpose',
        'requested_by_id',
        'head_approved_by_id',
        'head_approved_at',
        'supply_approved_by_id',
        'supply_approved_at',
        'issued_by_id',
        'issued_at',
        'acknowledged_by_id',
        'acknowledged_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'head_approved_at'    => 'datetime',
            'supply_approved_at'  => 'datetime',
            'issued_at'           => 'datetime',
            'acknowledged_at'     => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function requestingDept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_dept_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function headApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by_id');
    }

    public function supplyApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supply_approved_by_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RisItem::class);
    }

    // ── Status helpers ─────────────────────────────────────────────────────

    public function isDraft(): bool          { return $this->status === 'draft'; }
    public function isPendingHead(): bool    { return $this->status === 'pending_head'; }
    public function isPendingSupply(): bool  { return $this->status === 'pending_supply'; }
    public function isIssued(): bool         { return $this->status === 'issued'; }
    public function isCompleted(): bool      { return $this->status === 'completed'; }
    public function isRejected(): bool       { return $this->status === 'rejected'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft'          => 'Draft',
            'pending_head'   => 'Pending Head Approval',
            'pending_supply' => 'Pending Supply',
            'issued'         => 'Issued',
            'completed'      => 'Completed',
            'rejected'       => 'Rejected',
            default          => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'draft'          => 'gray',
            'pending_head'   => 'purple',
            'pending_supply' => 'blue',
            'issued'         => 'sky',
            'completed'      => 'emerald',
            'rejected'       => 'rose',
            default          => 'gray',
        };
    }

    // ── Auto-number generation ─────────────────────────────────────────────

    public static function generateRisNumber(): string
    {
        return DB::transaction(function () {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('RIS-%d-%04d', $year, $count + 1);
        });
    }
}
