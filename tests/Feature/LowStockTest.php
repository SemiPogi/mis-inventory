<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockTest extends TestCase
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

    private function makeItem(Department $dept, int $qty, int $minQty): Item
    {
        self::$seq++;
        return Item::create([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'min_stock_qty'      => $minQty,
            'department_id'      => $dept->id,
        ]);
    }

    /** @test */
    public function test_dashboard_shows_low_stock_items(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 2, minQty: 10);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($item->name)
            ->assertSee('Low Stock Alerts')
            ->assertSee('Low Stock');
    }

    /** @test */
    public function test_dashboard_does_not_show_low_stock_section_when_min_stock_zero(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $this->makeItem($dept, qty: 0, minQty: 0);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Low Stock Alerts');
    }

    /** @test */
    public function test_dashboard_low_stock_is_scoped_to_staff_dept(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff = $this->makeStaff($dept1);
        $itemOwn   = $this->makeItem($dept1, qty: 1, minQty: 5);
        $itemOther = $this->makeItem($dept2, qty: 1, minQty: 5);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($itemOwn->name)
            ->assertDontSee($itemOther->name);
    }

    /** @test */
    public function test_items_show_displays_low_stock_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 3, minQty: 10);

        $this->actingAs($staff)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Low Stock');
    }

    /** @test */
    public function test_items_show_displays_out_of_stock_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 0, minQty: 5);

        $this->actingAs($staff)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Out of stock');
    }
}
