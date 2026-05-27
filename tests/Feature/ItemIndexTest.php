<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_index_attaches_movement30(): void
    {
        $user = User::factory()->create();
        $item = Item::create([
            'name' => 'USB Cable', 'unit' => 'pcs',
            'current_qty' => 10, 'total_qty_received' => 10,
        ]);
        Transaction::create([
            'item_id' => $item->id, 'type' => 'received',
            'item_name_snapshot' => 'USB Cable', 'qty' => 5, 'unit' => 'pcs',
            'date_received' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('items.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $first = $items->first();

        $this->assertIsArray($first->movement30);
        $this->assertCount(30, $first->movement30);
    }
}
