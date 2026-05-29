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
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}