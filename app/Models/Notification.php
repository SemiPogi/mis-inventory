<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    // ── Factory method ─────────────────────────────────────────────────────

    public static function notify(int|User $user, string $type, string $title, string $message, array $data = []): self
    {
        return static::create([
            'user_id' => $user instanceof User ? $user->id : $user,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => empty($data) ? null : $data,
        ]);
    }
}
