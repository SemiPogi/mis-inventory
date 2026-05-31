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

    // ── cancel() tests ─────────────────────────────────────────────────────

    public function test_staff_can_cancel_own_pending_receive(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item  = $this->makeItem();
        $tx    = $this->makeReceive($item, $staff);

        $this->actingAs($staff)
            ->patch(route('transactions.cancel', $tx))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $tx->fresh()->head_approval_status);
    }

    public function test_staff_can_cancel_own_pending_release(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item  = $this->makeItem(['current_qty' => 10]);
        $tx    = $this->makeRelease($item, $staff);

        $this->actingAs($staff)
            ->patch(route('transactions.cancel', $tx))
            ->assertRedirect(route('transactions.index'));

        $this->assertEquals('cancelled', $tx->fresh()->head_approval_status);
    }

    public function test_staff_cannot_cancel_another_users_transaction(): void
    {
        $staffA = User::factory()->create(['role' => 'staff']);
        $staffB = User::factory()->create(['role' => 'staff']);
        $item   = $this->makeItem();
        $tx     = $this->makeReceive($item, $staffA);

        $this->actingAs($staffB)
            ->patch(route('transactions.cancel', $tx))
            ->assertForbidden();

        $this->assertEquals('pending', $tx->fresh()->head_approval_status);
    }

    public function test_cannot_cancel_an_already_approved_transaction(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item  = $this->makeItem();
        $tx    = $this->makeReceive($item, $staff, ['head_approval_status' => 'approved']);

        $this->actingAs($staff)
            ->patch(route('transactions.cancel', $tx))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals('approved', $tx->fresh()->head_approval_status);
    }

    public function test_cancel_deletes_item_when_qty_zero_and_no_other_active_transactions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item  = $this->makeItem(['current_qty' => 0]);
        $tx    = $this->makeReceive($item, $staff);

        $this->actingAs($staff)->patch(route('transactions.cancel', $tx));

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_cancel_keeps_item_when_other_active_transactions_exist(): void
    {
        $staffA = User::factory()->create(['role' => 'staff']);
        $staffB = User::factory()->create(['role' => 'staff']);
        $item   = $this->makeItem(['current_qty' => 0]);
        $tx     = $this->makeReceive($item, $staffA);
        // A second pending receive for the same item by another staff
        $this->makeReceive($item, $staffB);

        $this->actingAs($staffA)->patch(route('transactions.cancel', $tx));

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_cancel_keeps_item_when_qty_is_nonzero(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item  = $this->makeItem(['current_qty' => 5]);
        $tx    = $this->makeReceive($item, $staff);

        $this->actingAs($staff)->patch(route('transactions.cancel', $tx));

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }
}
