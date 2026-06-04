<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemsShowTabsTest extends TestCase
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
    public function test_items_show_has_three_tabs(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('History')
            ->assertSee('Audit Log');
    }

    /** @test */
    public function test_items_show_overview_tab_contains_stat_cards(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept, ['unit' => 'reams']);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Current Stock')
            ->assertSee('Total Received')
            ->assertSee('reams');
    }

    /** @test */
    public function test_items_show_history_tab_contains_transaction_table(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Transaction History');
    }

    /** @test */
    public function test_items_show_audit_log_tab_contains_log_section(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Audit Log');
    }
}
