<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class DepartmentTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_dept_id',
        'to_dept_id',
        'status',
        'purpose',
        'notes',
        'requested_by_id',
        'head_approved_by_id',
        'head_approved_at',
        'acknowledged_by_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'head_approved_at'  => 'datetime',
            'acknowledged_at'   => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function fromDept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_dept_id');
    }

    public function toDept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_dept_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function headApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepartmentTransferItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Status helpers ─────────────────────────────────────────────────────

    public function isPendingHead(): bool   { return $this->status === 'pending_head'; }
    public function isApproved(): bool      { return $this->status === 'approved'; }
    public function isRejected(): bool      { return $this->status === 'rejected'; }
    public function isCompleted(): bool     { return $this->status === 'completed'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending_head' => 'Pending Head Approval',
            'approved'     => 'Approved',
            'rejected'     => 'Rejected',
            'completed'    => 'Completed',
            default        => $this->status,
        };
    }

    // ── Auto-number ────────────────────────────────────────────────────────

    public static function generateTransferNumber(): string
    {
        return DB::transaction(function () {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('TRF-%d-%04d', $year, $count + 1);
        });
    }
}
