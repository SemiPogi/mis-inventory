<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\IarRecord;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IarTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeSupply(): Department
    {
        self::$seq++;
        return Department::create([
            'name'          => 'Supply ' . self::$seq,
            'code'          => 'SUP' . self::$seq,
            'is_active'     => true,
            'is_supply_hub' => true,
        ]);
    }

    private function makeSupplyStaff(Department $dept): User
    {
        return User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);
    }

    private function makeRegularStaff(): User
    {
        $dept = Department::create(['name' => 'Other ' . self::$seq, 'code' => 'OTH' . self::$seq, 'is_active' => true]);
        return User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);
    }

    private function iarPayload(): array
    {
        return [
            'supplier'           => 'ABC Supplies Corp',
            'purchase_order_no'  => 'PO-2026-001',
            'date_of_delivery'   => '2026-05-29',
            'date_of_inspection' => '2026-05-29',
            'items'              => [
                [
                    'item_name'     => 'Bond Paper A4',
                    'unit'          => 'ream',
                    'qty_delivered' => 10,
                    'qty_accepted'  => 10,
                    'unit_cost'     => 250.00,
                ],
            ],
        ];
    }

    public function test_supply_staff_can_create_iar(): void
    {
        $supply = $this->makeSupply();
        $staff  = $this->makeSupplyStaff($supply);

        $this->actingAs($staff)->post(route('iar.store'), $this->iarPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('iar_records', [
            'supplier' => 'ABC Supplies Corp',
            'status'   => 'draft',
        ]);
        $this->assertDatabaseHas('iar_items', [
            'item_name'    => 'Bond Paper A4',
            'qty_accepted' => 10,
        ]);
    }

    public function test_non_supply_staff_cannot_create_iar(): void
    {
        $this->makeSupply(); // create a supply hub so authorizeSupply fails correctly
        $outsider = $this->makeRegularStaff();

        $this->actingAs($outsider)->post(route('iar.store'), $this->iarPayload())
            ->assertForbidden();
    }

    public function test_accepting_iar_adds_items_to_supply_inventory(): void
    {
        $supply = $this->makeSupply();
        $staff  = $this->makeSupplyStaff($supply);

        $iar = IarRecord::create([
            'iar_number'    => 'IAR-2026-0001',
            'department_id' => $supply->id,
            'supplier'      => 'Test Vendor',
            'status'        => 'draft',
            'created_by_id' => $staff->id,
        ]);
        $iar->items()->create([
            'item_name'     => 'Ballpen',
            'unit'          => 'pcs',
            'qty_delivered' => 50,
            'qty_accepted'  => 48,
            'unit_cost'     => 5.00,
        ]);

        $this->actingAs($staff)->patch(route('iar.accept', $iar));

        $this->assertEquals('accepted', $iar->refresh()->status);
        $this->assertDatabaseHas('items', [
            'name'          => 'Ballpen',
            'department_id' => $supply->id,
            'current_qty'   => 48,
        ]);
    }

    public function test_accepting_iar_increments_existing_supply_item(): void
    {
        $supply = $this->makeSupply();
        $staff  = $this->makeSupplyStaff($supply);

        Item::create([
            'name'               => 'Folder',
            'category'           => 'Supplies',
            'unit'               => 'pcs',
            'total_qty_received' => 20,
            'current_qty'        => 20,
            'department_id'      => $supply->id,
        ]);

        $iar = IarRecord::create([
            'iar_number'    => 'IAR-2026-0002',
            'department_id' => $supply->id,
            'supplier'      => 'Vendor',
            'status'        => 'draft',
            'created_by_id' => $staff->id,
        ]);
        $iar->items()->create([
            'item_name'     => 'Folder',
            'unit'          => 'pcs',
            'qty_delivered' => 30,
            'qty_accepted'  => 30,
            'unit_cost'     => 10.00,
        ]);

        $this->actingAs($staff)->patch(route('iar.accept', $iar));

        $this->assertDatabaseHas('items', [
            'name'          => 'Folder',
            'department_id' => $supply->id,
            'current_qty'   => 50,
        ]);
    }

    public function test_rejecting_iar_does_not_add_to_inventory(): void
    {
        $supply = $this->makeSupply();
        $staff  = $this->makeSupplyStaff($supply);

        $iar = IarRecord::create([
            'iar_number'    => 'IAR-2026-0003',
            'department_id' => $supply->id,
            'supplier'      => 'Vendor',
            'status'        => 'draft',
            'created_by_id' => $staff->id,
        ]);
        $iar->items()->create([
            'item_name'     => 'Defective Item',
            'unit'          => 'pcs',
            'qty_delivered' => 10,
            'qty_accepted'  => 0,
            'unit_cost'     => 100.00,
        ]);

        $this->actingAs($staff)->patch(route('iar.reject', $iar), ['notes' => 'Items damaged']);

        $this->assertEquals('rejected', $iar->refresh()->status);
        $this->assertDatabaseMissing('items', ['name' => 'Defective Item']);
    }

    public function test_iar_number_is_auto_generated(): void
    {
        $supply = $this->makeSupply();
        $staff  = $this->makeSupplyStaff($supply);

        $this->actingAs($staff)->post(route('iar.store'), $this->iarPayload());

        $iar = IarRecord::first();
        $this->assertMatchesRegularExpression('/^IAR-\d{4}-\d{4}$/', $iar->iar_number);
    }
}
