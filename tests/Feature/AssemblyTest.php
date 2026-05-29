<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssemblyTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create(['name' => 'Dept ' . self::$seq, 'code' => 'D' . self::$seq, 'is_active' => true]);
    }

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);
    }

    private function makeItem(Department $dept, string $name = 'Part A', int $qty = 10): Item
    {
        return Item::create([
            'name'               => $name,
            'category'           => 'Parts',
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    public function test_staff_can_record_assembly(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $partA = $this->makeItem($dept, 'Wheel', 5);
        $partB = $this->makeItem($dept, 'Frame', 3);

        $response = $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Wheelchair',
            'output_unit'      => 'unit',
            'qty_produced'     => 1,
            'components'       => [
                ['item_id' => $partA->id, 'qty_used' => 2],
                ['item_id' => $partB->id, 'qty_used' => 1],
            ],
        ]);

        $asm = Assembly::first();
        $response->assertRedirect(route('assemblies.show', $asm));

        $this->assertDatabaseHas('assemblies', ['output_item_name' => 'Wheelchair']);
        $this->assertDatabaseHas('assembly_components', ['item_id' => $partA->id, 'qty_used' => 2]);
    }

    public function test_assembly_deducts_components_from_inventory(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $part  = $this->makeItem($dept, 'Bolt', 20);

        $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Frame',
            'output_unit'      => 'unit',
            'qty_produced'     => 2,
            'components'       => [['item_id' => $part->id, 'qty_used' => 6]],
        ]);

        $this->assertEquals(14, $part->refresh()->current_qty);
    }

    public function test_assembly_adds_output_to_inventory(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $part  = $this->makeItem($dept, 'Component', 10);

        $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Assembled Unit',
            'output_unit'      => 'unit',
            'qty_produced'     => 3,
            'components'       => [['item_id' => $part->id, 'qty_used' => 5]],
        ]);

        $this->assertDatabaseHas('items', [
            'name'          => 'Assembled Unit',
            'department_id' => $dept->id,
            'current_qty'   => 3,
        ]);
    }

    public function test_assembly_increments_existing_output_item(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $part  = $this->makeItem($dept, 'Part', 10);

        // Pre-existing output item
        Item::create([
            'name'               => 'Widget',
            'category'           => 'Products',
            'unit'               => 'unit',
            'total_qty_received' => 5,
            'current_qty'        => 5,
            'department_id'      => $dept->id,
        ]);

        $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Widget',
            'output_unit'      => 'unit',
            'qty_produced'     => 2,
            'components'       => [['item_id' => $part->id, 'qty_used' => 4]],
        ]);

        $this->assertDatabaseHas('items', [
            'name'          => 'Widget',
            'department_id' => $dept->id,
            'current_qty'   => 7,
        ]);
    }

    public function test_cannot_use_more_components_than_available(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $part  = $this->makeItem($dept, 'Scarce Part', 2);

        $response = $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Product',
            'output_unit'      => 'unit',
            'qty_produced'     => 1,
            'components'       => [['item_id' => $part->id, 'qty_used' => 5]],
        ]);

        $response->assertSessionHasErrors('components');
        $this->assertDatabaseCount('assemblies', 0);
    }

    public function test_assembly_number_is_auto_generated(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $part  = $this->makeItem($dept);

        $this->actingAs($staff)->post(route('assemblies.store'), [
            'output_item_name' => 'Output',
            'output_unit'      => 'unit',
            'qty_produced'     => 1,
            'components'       => [['item_id' => $part->id, 'qty_used' => 1]],
        ]);

        $asm = Assembly::first();
        $this->assertMatchesRegularExpression('/^ASM-\d{4}-\d{4}$/', $asm->assembly_number);
    }
}
