<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\RisItem;
use App\Models\RisRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RisTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeDept(array $attrs = []): Department
    {
        static $seq = 0;
        $seq++;
        return Department::create(array_merge([
            'name'      => "Department {$seq}",
            'code'      => "D{$seq}",
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

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeRis(User $requester, Department $dept, string $status = 'pending_head'): RisRequest
    {
        $ris = RisRequest::create([
            'ris_number'         => RisRequest::generateRisNumber(),
            'requesting_dept_id' => $dept->id,
            'status'             => $status,
            'purpose'            => 'Test purpose',
            'requested_by_id'    => $requester->id,
        ]);

        RisItem::create([
            'ris_request_id' => $ris->id,
            'item_name'      => 'Bond Paper A4',
            'unit'           => 'ream',
            'requested_qty'  => 5,
        ]);

        return $ris;
    }

    // ── Create & Submit ───────────────────────────────────────────────────

    public function test_staff_can_create_ris(): void
    {
        $dept = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $response = $this->actingAs($staff)->post(route('ris.store'), [
            'purpose' => 'Office supplies for May',
            'notes'   => 'Urgent',
            'items'   => [
                ['item_name' => 'Ballpen', 'unit' => 'pcs', 'requested_qty' => 10, 'stock_no' => '', 'remarks' => ''],
            ],
        ]);

        $ris = RisRequest::first();
        $response->assertRedirect(route('ris.show', $ris));
        $this->assertDatabaseHas('ris_requests', [
            'requesting_dept_id' => $dept->id,
            'status'             => 'pending_head',
            'purpose'            => 'Office supplies for May',
        ]);
        $this->assertDatabaseHas('ris_items', [
            'item_name'     => 'Ballpen',
            'requested_qty' => 10,
        ]);
    }

    public function test_ris_number_is_auto_generated(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $this->actingAs($staff)->post(route('ris.store'), [
            'purpose' => 'Test',
            'items'   => [['item_name' => 'Paper', 'unit' => 'ream', 'requested_qty' => 1]],
        ]);

        $ris = RisRequest::first();
        $this->assertMatchesRegularExpression('/^RIS-\d{4}-\d{4}$/', $ris->ris_number);
    }

    public function test_ris_requires_at_least_one_item(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $response = $this->actingAs($staff)->post(route('ris.store'), [
            'purpose' => 'Test',
            'items'   => [],
        ]);

        $response->assertSessionHasErrors('items');
    }

    // ── Head Approval ─────────────────────────────────────────────────────

    public function test_dept_head_can_approve_ris(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $ris   = $this->makeRis($staff, $dept);

        $this->actingAs($head)
            ->patch(route('ris.head.approve', $ris))
            ->assertRedirect(route('ris.head.index'));

        $ris->refresh();
        $this->assertEquals('pending_supply', $ris->status);
        $this->assertEquals($head->id, $ris->head_approved_by_id);
        $this->assertNotNull($ris->head_approved_at);
    }

    public function test_dept_head_can_reject_ris(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $ris   = $this->makeRis($staff, $dept);

        $this->actingAs($head)
            ->patch(route('ris.head.reject', $ris), ['notes' => 'Insufficient budget'])
            ->assertRedirect(route('ris.head.index'));

        $ris->refresh();
        $this->assertEquals('rejected', $ris->status);
        $this->assertEquals('Insufficient budget', $ris->notes);
    }

    public function test_non_head_staff_cannot_approve_ris(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $ris   = $this->makeRis($staff, $dept);

        $this->actingAs($staff)
            ->patch(route('ris.head.approve', $ris))
            ->assertForbidden();
    }

    public function test_head_cannot_approve_another_departments_ris(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff = $this->makeStaff($dept1);
        $head2 = $this->makeStaff($dept2, isHead: true);
        $ris   = $this->makeRis($staff, $dept1);

        $this->actingAs($head2)
            ->patch(route('ris.head.approve', $ris))
            ->assertForbidden();
    }

    // ── Supply Issuing ─────────────────────────────────────────────────────

    public function test_supply_staff_can_issue_ris(): void
    {
        $supply  = $this->makeDept(['is_supply_hub' => true]);
        $reqDept = $this->makeDept();
        $supplyUser = $this->makeStaff($supply);
        $requester  = $this->makeStaff($reqDept);

        $ris = $this->makeRis($requester, $reqDept, 'pending_supply');
        $risItem = $ris->items->first();

        $this->actingAs($supplyUser)
            ->patch(route('ris.supply.issue', $ris), [
                'issued_qty' => [$risItem->id => 3],
            ])
            ->assertRedirect(route('ris.supply.index'));

        $ris->refresh();
        $this->assertEquals('issued', $ris->status);
        $this->assertEquals($supplyUser->id, $ris->issued_by_id);
        $this->assertEquals(3, $ris->items->first()->refresh()->issued_qty);
    }

    public function test_issuing_deducts_from_supply_inventory(): void
    {
        $supply     = $this->makeDept(['is_supply_hub' => true]);
        $reqDept    = $this->makeDept();
        $supplyUser = $this->makeStaff($supply);
        $requester  = $this->makeStaff($reqDept);

        // Create supply stock item matching the RIS item name
        $stockItem = Item::create([
            'name'               => 'Bond Paper A4',
            'category'           => 'Supplies',
            'unit'               => 'ream',
            'total_qty_received' => 20,
            'current_qty'        => 20,
            'department_id'      => $supply->id,
        ]);

        $ris = $this->makeRis($requester, $reqDept, 'pending_supply');
        $risItem = $ris->items->first(); // Bond Paper A4 qty 5

        $this->actingAs($supplyUser)
            ->patch(route('ris.supply.issue', $ris), [
                'issued_qty' => [$risItem->id => 5],
            ]);

        $this->assertEquals(15, $stockItem->refresh()->current_qty);
    }

    public function test_non_supply_staff_cannot_issue_ris(): void
    {
        $supply  = $this->makeDept(['is_supply_hub' => true]);
        $reqDept = $this->makeDept();
        $outsider = $this->makeStaff($reqDept);

        $ris     = $this->makeRis($outsider, $reqDept, 'pending_supply');
        $risItem = $ris->items->first();

        $this->actingAs($outsider)
            ->patch(route('ris.supply.issue', $ris), [
                'issued_qty' => [$risItem->id => 1],
            ])
            ->assertForbidden();
    }

    // ── Acknowledge Receipt ────────────────────────────────────────────────

    public function test_dept_staff_can_acknowledge_receipt(): void
    {
        $reqDept = $this->makeDept();
        $staff   = $this->makeStaff($reqDept);

        $ris = $this->makeRis($staff, $reqDept, 'issued');
        $ris->items->first()->update(['issued_qty' => 5]);

        $this->actingAs($staff)
            ->patch(route('ris.acknowledge', $ris))
            ->assertRedirect(route('ris.show', $ris));

        $ris->refresh();
        $this->assertEquals('completed', $ris->status);
        $this->assertEquals($staff->id, $ris->acknowledged_by_id);
    }

    public function test_acknowledging_adds_items_to_dept_inventory(): void
    {
        $reqDept = $this->makeDept();
        $staff   = $this->makeStaff($reqDept);

        $ris = $this->makeRis($staff, $reqDept, 'issued');
        $ris->items->first()->update(['issued_qty' => 5]);

        $this->actingAs($staff)->patch(route('ris.acknowledge', $ris));

        $this->assertDatabaseHas('items', [
            'name'          => 'Bond Paper A4',
            'department_id' => $reqDept->id,
            'current_qty'   => 5,
        ]);
    }

    public function test_acknowledging_increments_existing_inventory(): void
    {
        $reqDept = $this->makeDept();
        $staff   = $this->makeStaff($reqDept);

        // Pre-existing item in dept inventory
        Item::create([
            'name'               => 'Bond Paper A4',
            'category'           => 'Supplies',
            'unit'               => 'ream',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $reqDept->id,
        ]);

        $ris = $this->makeRis($staff, $reqDept, 'issued');
        $ris->items->first()->update(['issued_qty' => 5]);

        $this->actingAs($staff)->patch(route('ris.acknowledge', $ris));

        $this->assertDatabaseHas('items', [
            'name'          => 'Bond Paper A4',
            'department_id' => $reqDept->id,
            'current_qty'   => 15,
        ]);
    }

    public function test_wrong_dept_staff_cannot_acknowledge(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $outsider = $this->makeStaff($dept2);

        $ris = $this->makeRis($staff1, $dept1, 'issued');
        $ris->items->first()->update(['issued_qty' => 5]);

        $this->actingAs($outsider)
            ->patch(route('ris.acknowledge', $ris))
            ->assertForbidden();
    }

    // ── Visibility & Access ────────────────────────────────────────────────

    public function test_staff_only_sees_own_dept_ris(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $staff2 = $this->makeStaff($dept2);

        $ris1 = $this->makeRis($staff1, $dept1);
        $ris2 = $this->makeRis($staff2, $dept2);

        $response = $this->actingAs($staff1)->get(route('ris.index'));
        $response->assertSee($ris1->ris_number);
        $response->assertDontSee($ris2->ris_number);
    }

    public function test_admin_sees_all_ris(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $staff2 = $this->makeStaff($dept2);
        $admin = $this->makeAdmin();

        $ris1 = $this->makeRis($staff1, $dept1);
        $ris2 = $this->makeRis($staff2, $dept2);

        $response = $this->actingAs($admin)->get(route('ris.index'));
        $response->assertSee($ris1->ris_number);
        $response->assertSee($ris2->ris_number);
    }

    public function test_staff_can_print_own_dept_ris_at_any_stage(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $ris   = $this->makeRis($staff, $dept, 'issued');

        $this->actingAs($staff)
            ->get(route('ris.print', $ris))
            ->assertOk();
    }

    public function test_staff_cannot_print_other_dept_ris(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $staff2 = $this->makeStaff($dept2);
        $ris   = $this->makeRis($staff1, $dept1, 'completed');

        $this->actingAs($staff2)
            ->get(route('ris.print', $ris))
            ->assertForbidden();
    }

    public function test_admin_can_print_any_ris(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $admin = $this->makeAdmin();
        $ris   = $this->makeRis($staff, $dept, 'completed');

        $this->actingAs($admin)
            ->get(route('ris.print', $ris))
            ->assertOk();
    }
}
