<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_provides_weekly_top_office_and_top_item(): void
    {
        $user = User::factory()->create();

        $item = Item::create([
            'name' => 'Bond Paper', 'unit' => 'reams',
            'current_qty' => 50, 'total_qty_received' => 50,
        ]);
        Transaction::create([
            'item_id' => $item->id,
            'type' => 'released',
            'item_name_snapshot' => 'Bond Paper',
            'qty' => 3, 'unit' => 'reams',
            'receiver_name' => 'Maria Santos',
            'released_to_office' => 'Radiology',
            'date_released' => now()->toDateString(),
            'acknowledgment_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('weeklyActivity')
            ->assertViewHas('topOffice', 'Radiology')
            ->assertViewHas('topItem', 'Bond Paper');

        $weekly = $response->viewData('weeklyActivity');
        $this->assertIsArray($weekly);
        $this->assertCount(7, $weekly);
    }
}
