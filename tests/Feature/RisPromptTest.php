<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RisPromptTest extends TestCase
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
            'name'               => 'Bond Paper A4',
            'unit'               => 'ream',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    public function test_approving_receive_flashes_suggest_ris(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx))
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('suggest_ris');
    }

    public function test_suggest_ris_flash_contains_transaction_details(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $response = $this->actingAs($head)->patch(route('approvals.approve', $tx));

        $risData = $response->getSession()->get('suggest_ris');
        $this->assertEquals('Bond Paper A4', $risData['item']);
        $this->assertEquals(5, $risData['qty']);
        $this->assertEquals('ream', $risData['unit']);
        $this->assertEquals('received', $risData['type']);
        $this->assertEquals($dept->id, $risData['dept_id']);
    }

    public function test_bulk_approve_flashes_suggest_ris_with_count(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx1 = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);
        $tx2 = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 2,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $response = $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]])
            ->assertSessionHas('suggest_ris');

        $risData = $response->getSession()->get('suggest_ris');
        $this->assertTrue($risData['bulk'] ?? false);
        $this->assertEquals(2, $risData['count']);
    }
}
