<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RisItem extends Model
{
    protected $fillable = [
        'ris_request_id',
        'stock_no',
        'item_name',
        'unit',
        'requested_qty',
        'issued_qty',
        'remarks',
    ];

    public function risRequest(): BelongsTo
    {
        return $this->belongsTo(RisRequest::class);
    }
}
