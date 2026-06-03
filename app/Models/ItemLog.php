<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLog extends Model
{
    /**
     * Logs are immutable — only created_at, no updated_at.
     * The DB sets created_at via useCurrent() default.
     */
    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'user_id',
        'action',
        'qty_change',
        'qty_before',
        'qty_after',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'qty_change' => 'integer',
            'qty_before' => 'integer',
            'qty_after'  => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write an immutable audit log entry for an item quantity change.
     *
     * @param  Item        $item       The item whose stock changed
     * @param  string      $action     One of: approved_receive, approved_release, cancelled, etc.
     * @param  int         $qtyChange  Positive = added, negative = deducted, 0 = no change
     * @param  int         $qtyBefore  Stock level BEFORE the change
     * @param  string|null $note       Optional context (e.g. "Transaction #42")
     */
    public static function record(
        Item $item,
        string $action,
        int $qtyChange,
        int $qtyBefore,
        ?string $note = null
    ): void {
        static::create([
            'item_id'    => $item->id,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'qty_change' => $qtyChange,
            'qty_before' => $qtyBefore,
            'qty_after'  => $qtyBefore + $qtyChange,
            'note'       => $note,
        ]);
    }
}
