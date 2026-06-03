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
}
