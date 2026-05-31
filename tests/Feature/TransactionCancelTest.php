<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCancelTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────

    private function makeItem(array $attrs = []): Item
    {
        return Item::create(array_merge([
            'name'               => 'Test Item',
            'unit'               => 'pcs',
            'current_qty'        => 0,
            'total_qty_received' => 0,
        ], $attrs));
    }

    private function makeReceive(Item $item, User $user, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => $item->unit,
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $user->id,
            'department_id'        => $user->department_id,
            'head_approval_status' => 'pending',
        ], $attrs));
    }

    private function makeRelease(Item $item, User $user, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                 => 'released',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => $item->unit,
            'released_to_office'   => 'Radiology',
            'receiver_name'        => 'Dr. Reyes',
            'date_released'        => now()->toDateString(),
            'released_by_user_id'  => $user->id,
            'department_id'        => $user->department_id,
            'head_approval_status' => 'pending',
        ], $attrs));
    }

    // ── Model helper tests ──────────────────────────────────────────────────

    public function test_is_cancelled_returns_true_when_status_is_cancelled(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem();
        $tx   = $this->makeReceive($item, $user, ['head_approval_status' => 'cancelled']);

        $this->assertTrue($tx->isCancelled());
    }

    public function test_is_cancelled_returns_false_when_status_is_pending(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem();
        $tx   = $this->makeReceive($item, $user);

        $this->assertFalse($tx->isCancelled());
    }
}
