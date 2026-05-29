<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentTransferItem extends Model
{
    protected $fillable = [
        'department_transfer_id',
        'item_id',
        'item_name_snapshot',
        'unit',
        'qty',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(DepartmentTransfer::class, 'department_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
