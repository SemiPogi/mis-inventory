<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReservationTest extends TestCase
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

    private function makeStaff(Department $dept, bool $isHead = false): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => $isHead,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Widget ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    public function test_release_index_includes_reservations_for_pending_releases(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 20);

        // Create a pending release — this should be counted as a reservation
        Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 7,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'pending',
            'acknowledgment_status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('release.index'));

        $response->assertViewHas('reservations', function ($reservations) use ($item) {
            return isset($reservations[$item->id]) && (int) $reservations[$item->id] === 7;
        });
    }

    public function test_release_index_excludes_approved_releases_from_reservations(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 20);

        // An approved release should NOT be a reservation
        Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 5,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'approved',
            'acknowledgment_status' => 'pending',
            'head_approved_by_id'   => $staff->id,
            'head_approved_at'      => now(),
        ]);

        $response = $this->actingAs($staff)->get(route('release.index'));

        $response->assertViewHas('reservations', function ($reservations) use ($item) {
            return ! isset($reservations[$item->id]) || (int) $reservations[$item->id] === 0;
        });
    }
}
