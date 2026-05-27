<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashVoucherTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    public function test_staff_can_submit_voucher_and_items_appear_in_inventory(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-12345',
            'store_name'        => 'National Bookstore',
            'releasing_officer' => 'Maria Reyes',
            'requested_amount'  => '2000',
            'transport_fee'     => '50',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Bond Paper', 'qty' => '5', 'unit' => 'reams', 'unit_cost' => '200'],
                ['item_name' => 'Ballpen',    'qty' => '10', 'unit' => 'pcs',  'unit_cost' => '10'],
            ],
        ]);

        $response->assertRedirect('/petty-cash');

        $this->assertDatabaseHas('petty_cash_vouchers', [
            'or_number'     => 'OR-12345',
            'store_name'    => 'National Bookstore',
            'total_amount'  => 1150.00,
            'change_amount' => 850.00,
            'status'        => 'submitted',
        ]);

        $this->assertDatabaseHas('items', ['name' => 'Bond Paper', 'current_qty' => 5]);
        $this->assertDatabaseHas('items', ['name' => 'Ballpen',    'current_qty' => 10]);

        $this->assertEquals(2, Transaction::where('type', 'received')
            ->where('received_from', 'National Bookstore')->count());
    }

    public function test_overspend_is_rejected(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-99',
            'store_name'        => 'SM',
            'releasing_officer' => 'Juan',
            'requested_amount'  => '500',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Printer', 'qty' => '1', 'unit' => 'pcs', 'unit_cost' => '600'],
            ],
        ]);

        $response->assertSessionHasErrors('total');
        $this->assertDatabaseCount('petty_cash_vouchers', 0);
    }

    public function test_existing_item_qty_is_incremented_not_duplicated(): void
    {
        $staff = $this->staff();
        Item::create(['name' => 'Bond Paper', 'unit' => 'reams', 'current_qty' => 10, 'total_qty_received' => 10]);

        $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-555',
            'store_name'        => 'Lim Store',
            'releasing_officer' => 'Pedro',
            'requested_amount'  => '2000',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Bond Paper', 'qty' => '3', 'unit' => 'reams', 'unit_cost' => '180'],
            ],
        ]);

        $this->assertDatabaseHas('items', ['name' => 'Bond Paper', 'current_qty' => 13]);
        $this->assertEquals(1, Item::where('name', 'Bond Paper')->count());
    }

    public function test_accounting_cannot_create_voucher(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/petty-cash/create')->assertForbidden();
    }

    public function test_accounting_can_settle_acknowledged_voucher(): void
    {
        $staff = $this->staff();
        $acc   = User::factory()->create(['role' => 'accounting', 'is_active' => true]);

        $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-ACK',
            'store_name'        => 'Store',
            'releasing_officer' => 'Officer',
            'requested_amount'  => '500',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Stapler', 'qty' => '1', 'unit' => 'pcs', 'unit_cost' => '200'],
            ],
        ]);

        $voucher = \App\Models\PettyCashVoucher::first();
        $this->actingAs($staff)->patch("/petty-cash/{$voucher->id}/acknowledge");

        $this->actingAs($acc)->patch("/petty-cash/{$voucher->id}/settle")
             ->assertRedirect('/petty-cash');

        $this->assertDatabaseHas('petty_cash_vouchers', [
            'id'     => $voucher->id,
            'status' => 'settled',
        ]);
    }
}
