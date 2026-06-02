<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkApproveTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D' . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeStaff(Department $dept, bool $isHead = false): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => $isHead,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Widget ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    private function makeReceive(Item $item, User $submitter, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'pcs',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'department_id'        => $submitter->department_id,
            'head_approval_status' => 'pending',
        ], $attrs));
    }

    private function makeRelease(Item $item, User $submitter, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 3,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $submitter->id,
            'department_id'         => $submitter->department_id,
            'head_approval_status'  => 'pending',
            'acknowledgment_status' => 'pending',
        ], $attrs));
    }

    // ── Happy path ─────────────────────────────────────────────────────────

    public function test_head_can_bulk_approve_multiple_receives(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 20);
        $staff = $this->makeStaff($dept);

        $tx1 = $this->makeReceive($item, $staff);
        $tx2 = $this->makeReceive($item, $staff);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('transactions', ['id' => $tx1->id, 'head_approval_status' => 'approved']);
        $this->assertDatabaseHas('transactions', ['id' => $tx2->id, 'head_approval_status' => 'approved']);
    }

    public function test_bulk_approve_updates_item_qty(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);
        $tx    = $this->makeReceive($item, $staff, ['qty' => 5]);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]]);

        $this->assertEquals(5, $item->fresh()->current_qty);
    }

    public function test_bulk_approve_sends_notification_per_transaction(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 20);
        $staff = $this->makeStaff($dept);

        $tx1 = $this->makeReceive($item, $staff);
        $tx2 = $this->makeReceive($item, $staff);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]]);

        $this->assertEquals(
            2,
            Notification::where('user_id', $staff->id)->where('type', 'tx_approved_receive')->count()
        );
    }

    // ── Partial failure ────────────────────────────────────────────────────

    public function test_bulk_approve_partial_failure_approves_passing_ones_and_warns(): void
    {
        $dept   = $this->makeDept();
        $head   = $this->makeStaff($dept, isHead: true);
        $item   = $this->makeItem($dept, 5);     // only 5 in stock
        $staff  = $this->makeStaff($dept);

        $goodTx = $this->makeReceive($item, $staff, ['qty' => 2]);
        $badTx  = $this->makeRelease($item, $staff, ['qty' => 10]); // exceeds stock

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$goodTx->id, $badTx->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('transactions', ['id' => $goodTx->id, 'head_approval_status' => 'approved']);
        $this->assertDatabaseHas('transactions', ['id' => $badTx->id,  'head_approval_status' => 'pending']);
    }

    public function test_bulk_approve_head_cannot_approve_other_dept_transactions(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $head1 = $this->makeStaff($dept1, isHead: true);
        $item  = $this->makeItem($dept2, 10);
        $tx    = $this->makeReceive($item, $this->makeStaff($dept2));

        $this->actingAs($head1)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'head_approval_status' => 'pending']);
    }

    // ── Validation & authorization ─────────────────────────────────────────

    public function test_bulk_approve_requires_ids(): void
    {
        $dept = $this->makeDept();
        $head = $this->makeStaff($dept, isHead: true);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), [])
            ->assertSessionHasErrors('ids');
    }

    public function test_staff_cannot_access_bulk_approve(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 10);
        $tx    = $this->makeReceive($item, $staff);

        $this->actingAs($staff)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]])
            ->assertForbidden();
    }
}
