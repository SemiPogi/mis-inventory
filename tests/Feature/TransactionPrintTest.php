<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPrintTest extends TestCase
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

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeItem(Department $dept): Item
    {
        return Item::create([
            'name'               => 'Bond Paper ' . self::$seq,
            'unit'               => 'ream',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ]);
    }

    private function makeReceiveTx(Item $item, User $submitter): Transaction
    {
        return Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => $item->unit,
            'received_from'        => 'Supply Dept',
            'ris_iar_number'       => 'IAR-001',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'acknowledgment_status'=> 'pending',
            'head_approval_status' => 'pending',
            'department_id'        => $item->department_id,
        ]);
    }

    private function makeReleaseTx(Item $item, User $submitter): Transaction
    {
        return Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 2,
            'unit'                  => $item->unit,
            'released_to_office'    => 'Nursing Unit 3',
            'receiver_name'         => 'Maria Santos',
            'receiver_designation'  => 'Head Nurse',
            'date_released'         => now()->toDateString(),
            'released_by_user_id'   => $submitter->id,
            'purpose'               => 'For ward use',
            'acknowledgment_status' => 'pending',
            'head_approval_status'  => 'pending',
            'department_id'         => $item->department_id,
        ]);
    }

    public function test_admin_can_print_any_transaction(): void
    {
        $dept   = $this->makeDept();
        $admin  = $this->makeAdmin();
        $staff  = $this->makeStaff($dept);
        $item   = $this->makeItem($dept);
        $tx     = $this->makeReceiveTx($item, $staff);

        $this->actingAs($admin)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee($item->name);
    }

    public function test_staff_can_print_own_dept_transaction(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee($item->name);
    }

    public function test_staff_cannot_print_other_dept_transaction(): void
    {
        $dept1  = $this->makeDept();
        $dept2  = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $staff2 = $this->makeStaff($dept2);
        $item   = $this->makeItem($dept1);
        $tx     = $this->makeReceiveTx($item, $staff1);

        $this->actingAs($staff2)
            ->get(route('transactions.print', $tx))
            ->assertForbidden();
    }

    public function test_receive_slip_shows_received_from_and_iar_number(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee('Supply Dept')
            ->assertSee('IAR-001')
            ->assertSee('ITEM RECEIPT SLIP');
    }

    public function test_release_slip_shows_receiver_and_office(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReleaseTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee('Nursing Unit 3')
            ->assertSee('Maria Santos')
            ->assertSee('ITEM RELEASE SLIP');
    }
}
