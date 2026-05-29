<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeDept(array $attrs = []): Department
    {
        return Department::create(array_merge([
            'name'      => 'Test Dept',
            'code'      => 'TEST',
            'is_active' => true,
        ], $attrs));
    }

    // ── Department CRUD (admin only) ──────────────────────────────────────

    public function test_admin_can_create_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Nursing Department',
            'code' => 'NURS',
        ])->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['code' => 'NURS']);
    }

    public function test_department_code_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeDept(['code' => 'NURS']);

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Another Nursing',
            'code' => 'NURS',
        ])->assertSessionHasErrors('code');
    }

    public function test_staff_cannot_access_departments(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);

        $this->actingAs($staff)->get(route('departments.index'))->assertForbidden();
    }

    public function test_only_one_supply_hub_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeDept(['code' => 'SUP1', 'is_supply_hub' => true]);

        // Creating a second supply hub should unset the first
        $this->actingAs($admin)->post(route('departments.store'), [
            'name'          => 'New Supply',
            'code'          => 'SUP2',
            'is_supply_hub' => '1',
        ])->assertRedirect();

        $this->assertEquals(1, Department::where('is_supply_hub', true)->count());
        $this->assertDatabaseHas('departments', ['code' => 'SUP2', 'is_supply_hub' => true]);
        $this->assertDatabaseHas('departments', ['code' => 'SUP1', 'is_supply_hub' => false]);
    }

    // ── Department scoping ────────────────────────────────────────────────

    public function test_staff_can_only_see_their_own_department_items(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);

        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $deptA->id]);

        $itemA = Item::factory()->create(['department_id' => $deptA->id, 'name' => 'Item A']);
        $itemB = Item::factory()->create(['department_id' => $deptB->id, 'name' => 'Item B']);

        $response = $this->actingAs($staff)->get(route('items.index'));
        $response->assertSee('Item A');
        $response->assertDontSee('Item B');
    }

    public function test_admin_sees_all_departments_items(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);
        $admin = User::factory()->create(['role' => 'admin']);

        Item::factory()->create(['department_id' => $deptA->id, 'name' => 'Item A']);
        Item::factory()->create(['department_id' => $deptB->id, 'name' => 'Item B']);

        $response = $this->actingAs($admin)->get(route('items.index'));
        $response->assertSee('Item A');
        $response->assertSee('Item B');
    }

    public function test_staff_cannot_view_item_from_another_department(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);
        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $deptA->id]);
        $itemB = Item::factory()->create(['department_id' => $deptB->id]);

        $this->actingAs($staff)->get(route('items.show', $itemB))->assertForbidden();
    }

    // ── User department assignment ────────────────────────────────────────

    public function test_admin_can_assign_user_to_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dept  = $this->makeDept();
        $user  = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => 'staff',
            'department_id' => $dept->id,
        ])->assertRedirect(route('users.index'));

        $this->assertEquals($dept->id, $user->fresh()->department_id);
    }

    public function test_admin_can_designate_department_head(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dept  = $this->makeDept();
        $user  = User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => '1',
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->is_head);
    }
}
