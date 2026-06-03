<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
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
}
