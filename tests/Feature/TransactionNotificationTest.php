<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionNotificationTest extends TestCase
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

    // ── tx_submitted ───────────────────────────────────────────────────────

    public function test_receive_submission_notifies_dept_head(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);

        $this->actingAs($staff)->post(route('receive.store'), [
            'name'          => 'New Widget',
            'qty'           => 5,
            'date_received' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $head->id,
            'type'    => 'tx_submitted',
        ]);
    }
}
