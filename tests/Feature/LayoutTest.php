<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_new_sidebar_with_primary_themed_active_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('MIS Office', false)
            ->assertSee('La Union Medical Center', false)
            ->assertSee('href="' . route('receive.index') . '"', false)
            ->assertSee('bg-primary-50', false);
    }
}
