<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAlertsTabTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'   . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'admin',
            'name'          => 'Test Admin',
            'department_id' => $dept->id,
        ]);
    }

    private function makeItem(Department $dept, array $overrides = []): Item
    {
        self::$seq++;
        return Item::create(array_merge([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ], $overrides));
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_expiry_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['expiry_date' => now()->addDays(10)->toDateString()]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Expiry (');
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_low_stock_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['current_qty' => 2, 'min_stock_qty' => 10]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Low Stock (');
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_warranty_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['warranty_expiry_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Warranty (');
    }

    /** @test */
    public function test_dashboard_hides_alerts_card_when_no_alerts(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept); // plain item — no expiry, no warranty, no low-stock threshold

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Expiry (')
            ->assertDontSee('Low Stock (')
            ->assertDontSee('Warranty (');
    }

    /** @test */
    public function test_dashboard_hides_tab_when_no_items_in_that_category(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        // Only low stock — expiry and warranty tabs must not appear
        $this->makeItem($dept, ['current_qty' => 1, 'min_stock_qty' => 10]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Low Stock (')
            ->assertDontSee('Expiry (')
            ->assertDontSee('Warranty (');
    }
}
