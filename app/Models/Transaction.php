<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'type',
        'item_id',
        'item_name_snapshot',
        'qty',
        'unit',
        'received_from',
        'ris_iar_number',
        'date_received',
        'received_by_user_id',
        'released_to_office',
        'receiver_name',
        'receiver_designation',
        'released_by_user_id',
        'purpose',
        'date_released',
        'acknowledgment_status',
        'acknowledged_by_name',
        'acknowledged_date',
        'acknowledgment_remarks',
        'remarks',
        'department_id',
        // Head approval
        'head_approval_status',
        'head_approved_by_id',
        'head_approved_at',
        'head_rejection_notes',
    ];

    protected $casts = [
        'head_approved_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by_id');
    }

    /** True when waiting for head/admin action. */
    public function isPendingApproval(): bool
    {
        return $this->head_approval_status === 'pending';
    }

    /** True when approved (or legacy null — treated as pre-approved). */
    public function isApproved(): bool
    {
        return is_null($this->head_approval_status) || $this->head_approval_status === 'approved';
    }

    /** True when rejected. */
    public function isRejected(): bool
    {
        return $this->head_approval_status === 'rejected';
    }
}
