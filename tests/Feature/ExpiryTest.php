<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryTest extends TestCase
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

    // ── Receive form saves expiry_date ──────────────────────────────────────

    public function test_receive_form_saves_expiry_date(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $this->actingAs($staff)->post(route('receive.store'), [
            'name'          => 'Paracetamol 500mg',
            'category'      => 'Consumables',
            'unit'          => 'box',
            'qty'           => 20,
            'date_received' => now()->toDateString(),
            'expiry_date'   => '2026-12-31',
        ]);

        $item = Item::where('name', 'Paracetamol 500mg')->firstOrFail();
        $this->assertEquals('2026-12-31', $item->expiry_date->toDateString());
    }

    public function test_receive_form_accepts_no_expiry_date(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $this->actingAs($staff)->post(route('receive.store'), [
            'name'          => 'Ballpen',
            'unit'          => 'pcs',
            'qty'           => 50,
            'date_received' => now()->toDateString(),
            // no expiry_date
        ]);

        $this->assertDatabaseHas('items', [
            'name'        => 'Ballpen',
            'expiry_date' => null,
        ]);
    }

    // ── Item model expiry helpers ───────────────────────────────────────────

    public function test_item_expiry_status_expired(): void
    {
        $item = new Item(['expiry_date' => now()->subDay()]);
        $this->assertEquals('expired', $item->expiryStatus());
        $this->assertTrue($item->isExpired());
        $this->assertFalse($item->isExpiringSoon());
    }

    public function test_item_expiry_status_soon(): void
    {
        $item = new Item(['expiry_date' => now()->addDays(15)]);
        $this->assertEquals('soon', $item->expiryStatus());
        $this->assertFalse($item->isExpired());
        $this->assertTrue($item->isExpiringSoon());
    }

    public function test_item_expiry_status_ok(): void
    {
        $item = new Item(['expiry_date' => now()->addDays(60)]);
        $this->assertEquals('ok', $item->expiryStatus());
        $this->assertFalse($item->isExpired());
        $this->assertFalse($item->isExpiringSoon());
    }

    public function test_item_expiry_status_null_when_no_date(): void
    {
        $item = new Item(['expiry_date' => null]);
        $this->assertNull($item->expiryStatus());
    }

    // ── Items list shows expiry badges ──────────────────────────────────────

    public function test_items_list_shows_expired_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        Item::create([
            'name'               => 'Expired Drug',
            'category'           => 'Consumables',
            'unit'               => 'box',
            'total_qty_received' => 5,
            'current_qty'        => 5,
            'department_id'      => $dept->id,
            'expiry_date'        => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($staff)->get(route('items.index'));

        $response->assertStatus(200);
        $response->assertSee('Expired');
    }

    public function test_items_list_shows_expires_soon_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        Item::create([
            'name'               => 'Expiring Soon Drug',
            'category'           => 'Consumables',
            'unit'               => 'box',
            'total_qty_received' => 5,
            'current_qty'        => 5,
            'department_id'      => $dept->id,
            'expiry_date'        => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($staff)->get(route('items.index'));

        $response->assertStatus(200);
        $response->assertSee('Expires soon');
    }

    public function test_items_list_shows_no_expiry_badge_when_no_date(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        Item::create([
            'name'               => 'Ballpen',
            'unit'               => 'pcs',
            'total_qty_received' => 50,
            'current_qty'        => 50,
            'department_id'      => $dept->id,
        ]);

        $response = $this->actingAs($staff)->get(route('items.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Expired');
        $response->assertDontSee('Expires soon');
    }

    // ── Item show displays expiry card ──────────────────────────────────────

    public function test_item_show_displays_expiry_date(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        $item = Item::create([
            'name'               => 'Medicine',
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
            'expiry_date'        => '2026-06-30',
        ]);

        $response = $this->actingAs($staff)->get(route('items.show', $item));

        $response->assertStatus(200);
        $response->assertSee('Expiry Date');
        $response->assertSee('Jun 30, 2026');
    }

    // ── Dashboard shows expiry alert section ───────────────────────────────

    public function test_dashboard_shows_expiry_alert_for_expiring_items(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        Item::create([
            'name'               => 'Expiring Soon Medicine',
            'unit'               => 'box',
            'total_qty_received' => 5,
            'current_qty'        => 5,
            'department_id'      => $dept->id,
            'expiry_date'        => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Expiry Alerts');
        $response->assertSee('Expiring Soon Medicine');
    }

    public function test_dashboard_hides_expiry_section_when_none(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);

        // Item with no expiry
        Item::create([
            'name'               => 'Stapler',
            'unit'               => 'pcs',
            'total_qty_received' => 3,
            'current_qty'        => 3,
            'department_id'      => $dept->id,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Expiry Alerts');
    }
}
