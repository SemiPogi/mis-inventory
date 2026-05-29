<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Assembly extends Model
{
    use HasFactory;

    protected $fillable = [
        'assembly_number',
        'department_id',
        'output_item_name',
        'output_unit',
        'qty_produced',
        'notes',
        'assembled_by_id',
        'assembled_at',
    ];

    protected function casts(): array
    {
        return [
            'assembled_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assembledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assembled_by_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssemblyComponent::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Auto-number ────────────────────────────────────────────────────────

    public static function generateAssemblyNumber(): string
    {
        return DB::transaction(function () {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('ASM-%d-%04d', $year, $count + 1);
        });
    }
}
