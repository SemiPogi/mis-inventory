<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    // ── List ──────────────────────────────────────────────────────────────

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function test_admin_can_create_staff_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name'                  => 'Maria Santos',
            'email'                 => 'maria@lumc.gov.ph',
            'role'                  => 'staff',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email'     => 'maria@lumc.gov.ph',
            'role'      => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_accounting_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name'                  => 'Jose Reyes',
            'email'                 => 'jose@lumc.gov.ph',
            'role'                  => 'accounting',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'jose@lumc.gov.ph', 'role' => 'accounting']);
    }

    public function test_store_requires_unique_email(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $existing = User::factory()->create(['email' => 'taken@lumc.gov.ph']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name'                  => 'Duplicate',
            'email'                 => 'taken@lumc.gov.ph',
            'role'                  => 'staff',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_store_rejects_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name'                  => 'Hacker',
            'email'                 => 'hacker@example.com',
            'role'                  => 'superuser',   // invalid
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function test_admin_can_update_user_name_and_role(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('users.update', $target), [
            'name'  => 'Updated Name',
            'email' => $target->email,
            'role'  => 'accounting',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id'   => $target->id,
            'name' => 'Updated Name',
            'role' => 'accounting',
        ]);
    }

    public function test_password_update_is_optional(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $oldHash = $target->password;

        $this->actingAs($admin)->patch(route('users.update', $target), [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
            // password intentionally omitted
        ])->assertRedirect(route('users.index'));

        $this->assertEquals($oldHash, $target->fresh()->password);
    }

    // ── Deactivate / Reactivate ──────────────────────────────────────────

    public function test_admin_can_deactivate_another_user(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('users.deactivate', $target))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_admin_can_reactivate_a_user(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'staff', 'is_active' => false]);

        $this->actingAs($admin)->patch(route('users.deactivate', $target))
            ->assertRedirect();

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('users.deactivate', $admin))
            ->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);   // unchanged
    }

    // ── Authorization guards ──────────────────────────────────────────────

    public function test_staff_cannot_access_user_management(): void
    {
        $staff  = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'accounting', 'is_active' => true]);

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('users.store'), [])->assertForbidden();
        $this->actingAs($staff)->get(route('users.edit', $target))->assertForbidden();
        $this->actingAs($staff)->patch(route('users.update', $target), [])->assertForbidden();
    }
}
