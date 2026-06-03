<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'  . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    /** @test */
    public function test_item_log_record_creates_row_correctly(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 100);

        $this->actingAs($admin);

        ItemLog::record($item, 'approved_receive', 5, 100, 'Transaction #1');

        $log = ItemLog::first();
        $this->assertNotNull($log);
        $this->assertEquals($item->id, $log->item_id);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(5, $log->qty_change);
        $this->assertEquals(100, $log->qty_before);
        $this->assertEquals(105, $log->qty_after);
        $this->assertEquals('Transaction #1', $log->note);
    }

    /** @test */
    public function test_item_logs_relationship_returns_logs_in_descending_order(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 100);

        $this->actingAs($admin);

        ItemLog::record($item, 'approved_receive', 5, 100);
        ItemLog::record($item, 'approved_release', -2, 105);

        $logs = $item->logs()->get();
        $this->assertCount(2, $logs);
        // Latest first
        $this->assertEquals('approved_release', $logs->first()->action);
    }

    private function makeReceiveTx(Item $item, User $submitter): \App\Models\Transaction
    {
        return \App\Models\Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => $item->unit,
            'received_from'        => 'Supply',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'acknowledgment_status'=> 'pending',
            'head_approval_status' => 'pending',
            'department_id'        => $item->department_id,
        ]);
    }

    private function makeReleaseTx(Item $item, User $submitter): \App\Models\Transaction
    {
        return \App\Models\Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 2,
            'unit'                  => $item->unit,
            'released_to_office'    => 'Nursing Unit',
            'receiver_name'         => 'Nurse',
            'date_released'         => now()->toDateString(),
            'released_by_user_id'   => $submitter->id,
            'acknowledgment_status' => 'pending',
            'head_approval_status'  => 'pending',
            'department_id'         => $item->department_id,
        ]);
    }

    private function makeHead(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => true,
        ]);
    }

    /** @test */
    public function test_approving_receive_writes_approved_receive_log(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeHead($dept);
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 10);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(3, $log->qty_change);
        $this->assertEquals(10, $log->qty_before);
        $this->assertEquals(13, $log->qty_after);
    }

    /** @test */
    public function test_approving_release_writes_approved_release_log(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeHead($dept);
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 10);
        $tx    = $this->makeReleaseTx($item, $staff);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_release', $log->action);
        $this->assertEquals(-2, $log->qty_change);
        $this->assertEquals(10, $log->qty_before);
        $this->assertEquals(8, $log->qty_after);
    }

    /** @test */
    public function test_cancelling_pending_receive_writes_cancelled_log(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 5);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->patch(route('transactions.cancel', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('cancelled', $log->action);
        $this->assertEquals(0, $log->qty_change);
    }

    /** @test */
    public function test_auto_approve_receive_writes_log_entry(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $admin->update(['department_id' => $dept->id]);

        $this->actingAs($admin)
            ->post(route('receive.store'), [
                'name'          => 'Test Paper',
                'qty'           => 5,
                'unit'          => 'ream',
                'date_received' => now()->toDateString(),
            ]);

        $log = ItemLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(5, $log->qty_change);
    }

    /** @test */
    public function test_auto_approve_release_writes_log_entry(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 10);
        $admin->update(['department_id' => $dept->id]);

        $this->actingAs($admin)
            ->post(route('release.store'), [
                'item_id'            => $item->id,
                'qty'                => 3,
                'released_to_office' => 'Nursing Unit',
                'receiver_name'      => 'Nurse',
                'date_released'      => now()->toDateString(),
            ]);

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_release', $log->action);
        $this->assertEquals(-3, $log->qty_change);
    }
}
