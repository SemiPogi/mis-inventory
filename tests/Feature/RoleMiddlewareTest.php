<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_users_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/users')->assertOk();
    }

    public function test_staff_cannot_access_users_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get('/users')->assertForbidden();
    }

    public function test_accounting_cannot_access_users_page(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/users')->assertForbidden();
    }

    public function test_staff_can_access_reports(): void
    {
        // Reports are now available to all roles, scoped to their department
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get('/reports')->assertOk();
    }

    public function test_accounting_can_access_reports(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/reports')->assertOk();
    }

    public function test_inactive_user_gets_403_on_protected_routes(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->actingAs($user)->get('/')->assertForbidden();
    }
}
