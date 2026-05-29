<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentTransfer;
use App\Models\DepartmentTransferItem;
use App\Models\Item;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(array $attrs = []): Department
    {
        self::$seq++;
        return Department::create(array_merge([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D' . self::$seq,
            'is_active' => true,
        ], $attrs));
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
            'name'               => 'Test Item ' . rand(1000, 9999),
            'category'           => 'Supplies',
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    // ── Create Transfer ────────────────────────────────────────────────────

    public function test_staff_can_create_transfer(): void
    {
        $from = $this->makeDept();
        $to   = $this->makeDept();
        $staff = $this->makeStaff($from);
        $item  = $this->makeItem($from, 10);

        $response = $this->actingAs($staff)->post(route('transfers.store'), [
            'to_dept_id' => $to->id,
            'purpose'    => 'Sharing supplies',
            'items'      => [['item_id' => $item->id, 'qty' => 3]],
        ]);

        $transfer = DepartmentTransfer::first();
        $response->assertRedirect(route('transfers.show', $transfer));
        $this->assertDatabaseHas('department_transfers', ['status' => 'pending_head']);
        $this->assertDatabaseHas('department_transfer_items', ['item_id' => $item->id, 'qty' => 3]);
    }

    public function test_cannot_transfer_more_than_available_qty(): void
    {
        $from  = $this->makeDept();
        $to    = $this->makeDept();
        $staff = $this->makeStaff($from);
        $item  = $this->makeItem($from, 5);

        $response = $this->actingAs($staff)->post(route('transfers.store'), [
            'to_dept_id' => $to->id,
            'purpose'    => 'Test',
            'items'      => [['item_id' => $item->id, 'qty' => 10]],  // more than available
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('department_transfers', 0);
    }

    public function test_cannot_transfer_to_own_department(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);

        $response = $this->actingAs($staff)->post(route('transfers.store'), [
            'to_dept_id' => $dept->id,
            'purpose'    => 'Test',
            'items'      => [['item_id' => $item->id, 'qty' => 1]],
        ]);

        $response->assertSessionHasErrors('to_dept_id');
    }

    // ── Head Approval ──────────────────────────────────────────────────────

    public function test_head_can_approve_transfer(): void
    {
        $from  = $this->makeDept();
        $to    = $this->makeDept();
        $staff = $this->makeStaff($from);
        $head  = $this->makeStaff($from, isHead: true);
        $item  = $this->makeItem($from, 10);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0001',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $to->id,
            'status'          => 'pending_head',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);
        DepartmentTransferItem::create([
            'department_transfer_id' => $transfer->id,
            'item_id'                => $item->id,
            'item_name_snapshot'     => $item->name,
            'unit'                   => 'pcs',
            'qty'                    => 3,
        ]);

        $this->actingAs($head)
            ->patch(route('transfers.approve', $transfer))
            ->assertRedirect(route('transfers.head.index'));

        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);

        // Stock deducted from source
        $this->assertEquals(7, $item->refresh()->current_qty);
    }

    public function test_head_can_reject_transfer(): void
    {
        $from  = $this->makeDept();
        $to    = $this->makeDept();
        $staff = $this->makeStaff($from);
        $head  = $this->makeStaff($from, isHead: true);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0002',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $to->id,
            'status'          => 'pending_head',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);

        $this->actingAs($head)
            ->patch(route('transfers.reject', $transfer), ['notes' => 'Not approved'])
            ->assertRedirect(route('transfers.head.index'));

        $this->assertEquals('rejected', $transfer->refresh()->status);
    }

    public function test_head_cannot_approve_another_depts_transfer(): void
    {
        $from  = $this->makeDept();
        $other = $this->makeDept();
        $staff = $this->makeStaff($from);
        $otherHead = $this->makeStaff($other, isHead: true);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0003',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $other->id,
            'status'          => 'pending_head',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);

        $this->actingAs($otherHead)
            ->patch(route('transfers.approve', $transfer))
            ->assertForbidden();
    }

    // ── Acknowledge ────────────────────────────────────────────────────────

    public function test_dest_dept_staff_can_acknowledge_transfer(): void
    {
        $from  = $this->makeDept();
        $to    = $this->makeDept();
        $staff = $this->makeStaff($from);
        $toStaff = $this->makeStaff($to);
        $item  = $this->makeItem($from, 10);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0004',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $to->id,
            'status'          => 'approved',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);
        DepartmentTransferItem::create([
            'department_transfer_id' => $transfer->id,
            'item_id'                => $item->id,
            'item_name_snapshot'     => $item->name,
            'unit'                   => 'pcs',
            'qty'                    => 4,
        ]);

        $this->actingAs($toStaff)
            ->patch(route('transfers.acknowledge', $transfer))
            ->assertRedirect(route('transfers.show', $transfer));

        $this->assertEquals('completed', $transfer->refresh()->status);

        // Items added to destination inventory
        $this->assertDatabaseHas('items', [
            'name'          => $item->name,
            'department_id' => $to->id,
            'current_qty'   => 4,
        ]);
    }

    public function test_source_dept_cannot_acknowledge_transfer(): void
    {
        $from    = $this->makeDept();
        $to      = $this->makeDept();
        $staff   = $this->makeStaff($from);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0005',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $to->id,
            'status'          => 'approved',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('transfers.acknowledge', $transfer))
            ->assertForbidden();
    }

    // ── Notifications ──────────────────────────────────────────────────────

    public function test_approving_transfer_creates_notification(): void
    {
        $from  = $this->makeDept();
        $to    = $this->makeDept();
        $staff = $this->makeStaff($from);
        $head  = $this->makeStaff($from, isHead: true);
        $item  = $this->makeItem($from, 10);

        $transfer = DepartmentTransfer::create([
            'transfer_number' => 'TRF-2026-0006',
            'from_dept_id'    => $from->id,
            'to_dept_id'      => $to->id,
            'status'          => 'pending_head',
            'purpose'         => 'Test',
            'requested_by_id' => $staff->id,
        ]);
        DepartmentTransferItem::create([
            'department_transfer_id' => $transfer->id,
            'item_id'                => $item->id,
            'item_name_snapshot'     => $item->name,
            'unit'                   => 'pcs',
            'qty'                    => 1,
        ]);

        $this->actingAs($head)->patch(route('transfers.approve', $transfer));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'transfer_approved',
        ]);
    }
}
