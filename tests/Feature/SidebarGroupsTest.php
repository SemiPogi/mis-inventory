<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarGroupsTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'   . self::$seq,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_sidebar_shows_all_sections_for_admin(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create([
            'role'          => 'admin',
            'name'          => 'Test Admin',
            'department_id' => $dept->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Approvals')
            ->assertSee('Requisitions')
            ->assertSee('Operations')
            ->assertSee('Finance')
            ->assertSee('Admin');
    }

    /** @test */
    public function test_sidebar_shows_basic_sections_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Requisitions')
            ->assertSee('Operations')
            ->assertSee('Finance');
    }

    /** @test */
    public function test_sidebar_hides_approvals_section_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Approvals<');
    }

    /** @test */
    public function test_sidebar_hides_admin_section_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Admin<');
    }

    /** @test */
    public function test_sidebar_shows_approvals_section_for_head(): void
    {
        $dept = $this->makeDept();
        $head = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Head',
            'department_id' => $dept->id,
            'is_head'       => true,
        ]);

        $this->actingAs($head)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Approvals');
    }
}
