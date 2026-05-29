<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IarItem extends Model
{
    protected $fillable = [
        'iar_record_id',
        'item_name',
        'unit',
        'qty_delivered',
        'qty_accepted',
        'unit_cost',
        'description',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
        ];
    }

    public function iarRecord(): BelongsTo
    {
        return $this->belongsTo(IarRecord::class);
    }

    public function totalCost(): float
    {
        return $this->qty_accepted * $this->unit_cost;
    }
}
