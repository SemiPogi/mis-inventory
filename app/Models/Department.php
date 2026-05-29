<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'responsibility_center_code',
        'is_supply_hub',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_supply_hub' => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function head(): HasOne
    {
        return $this->hasOne(User::class)->where('is_head', true);
    }

    /** Returns the Supply hub department, or null if not configured. */
    public static function supplyHub(): ?self
    {
        return static::where('is_supply_hub', true)->where('is_active', true)->first();
    }
}
