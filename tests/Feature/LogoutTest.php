<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout_via_post_admin_logout_with_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => url('/admin/login'),
            ]);

        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_authenticated_user_can_logout_via_post_admin_logout_with_redirect(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->post('/admin/logout');

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_user_can_logout_via_legacy_post_logout_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => url('/admin/login'),
            ]);

        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_guest_can_access_legacy_logout_without_error(): void
    {
        $response = $this->postJson('/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => url('/admin/login'),
            ]);
    }
}
