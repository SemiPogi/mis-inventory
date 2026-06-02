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

    public function test_release_submission_notifies_dept_head(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 10);

        $this->actingAs($staff)->post(route('release.store'), [
            'item_id'            => $item->id,
            'qty'                => 3,
            'released_to_office' => 'ICU',
            'receiver_name'      => 'Dr. Santos',
            'date_released'      => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $head->id,
            'type'    => 'tx_submitted',
        ]);
    }

    // ── tx_approved ────────────────────────────────────────────────────────

    public function test_approving_receive_notifies_submitter(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'pcs',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $this->actingAs($head)->patch(route('approvals.approve', $tx))->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'tx_approved_receive',
        ]);
    }

    public function test_approving_release_notifies_submitter(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 10);

        $tx = Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 3,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'pending',
        ]);

        $this->actingAs($head)->patch(route('approvals.approve', $tx))->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'tx_approved_release',
        ]);
    }
}
