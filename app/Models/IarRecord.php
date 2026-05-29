<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class IarRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'iar_number',
        'department_id',
        'supplier',
        'purchase_order_no',
        'date_of_delivery',
        'date_of_inspection',
        'status',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_delivery'   => 'date',
            'date_of_inspection' => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IarItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Status helpers ─────────────────────────────────────────────────────

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft'    => 'Draft',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            default    => $this->status,
        };
    }

    public function totalValue(): float
    {
        return $this->items->sum(fn($i) => $i->qty_accepted * $i->unit_cost);
    }

    // ── Auto-number ────────────────────────────────────────────────────────

    public static function generateIarNumber(): string
    {
        return DB::transaction(function () {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('IAR-%d-%04d', $year, $count + 1);
        });
    }
}
