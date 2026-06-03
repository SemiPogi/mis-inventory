<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyTest extends TestCase
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

    private function makeItem(Department $dept, array $overrides = []): Item
    {
        return Item::create(array_merge([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ], $overrides));
    }

    /** @test */
    public function test_warranty_status_returns_null_when_no_expiry_date(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept); // no warranty_expiry_date
        $this->assertNull($item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expired_for_past_date(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->subDay()->toDateString(),
        ]);
        $this->assertEquals('expired', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_within_30_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(15)->toDateString(),
        ]);
        $this->assertEquals('expiring', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_soon_within_90_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(60)->toDateString(),
        ]);
        $this->assertEquals('expiring-soon', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_active_for_over_90_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(91)->toDateString(),
        ]);
        $this->assertEquals('active', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_at_exactly_30_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->assertEquals('expiring', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_soon_at_exactly_90_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(90)->toDateString(),
        ]);
        $this->assertEquals('expiring-soon', $item->warrantyStatus());
    }

    /** @test */
    public function test_receive_store_saves_warranty_fields_for_new_item(): void
    {
        $dept  = $this->makeDept();
        $admin = \App\Models\User::factory()->create([
            'role'          => 'admin',
            'department_id' => $dept->id,
        ]);

        $this->actingAs($admin)
            ->post(route('receive.store'), [
                'name'                  => 'HP Laptop',
                'qty'                   => 1,
                'unit'                  => 'unit',
                'date_received'         => now()->toDateString(),
                'warranty_provider'     => 'HP Philippines',
                'warranty_reference_no' => 'WR-2024-001',
                'warranty_expiry_date'  => now()->addYears(2)->toDateString(),
                'warranty_notes'        => 'Parts and labor',
            ])
            ->assertRedirect();

        $item = \App\Models\Item::where('name', 'HP Laptop')->first();
        $this->assertNotNull($item);
        $this->assertEquals('HP Philippines', $item->warranty_provider);
        $this->assertEquals('WR-2024-001', $item->warranty_reference_no);
        $this->assertNotNull($item->warranty_expiry_date);
        $this->assertEquals('Parts and labor', $item->warranty_notes);
    }
}
