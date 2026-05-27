<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashItem extends Model
{
    protected $fillable = [
        'petty_cash_voucher_id', 'item_id',
        'item_name', 'qty', 'unit', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'qty'        => 'decimal:2',
        'unit_cost'  => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'petty_cash_voucher_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
