<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_logout_redirects_to_login_and_does_not_log_out_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/logout');
        $response->assertRedirect('/admin/login');

        // User must remain authenticated
        $this->assertAuthenticatedAs($user);
    }

    public function test_post_logout_requires_authentication(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->postJson('/admin/logout');
        $response->assertStatus(401);

        $responseLegacy = $this->postJson('/logout');
        $responseLegacy->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_via_post_admin_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/admin/logout');
        $response->assertRedirect('/admin/login');

        $this->assertGuest('web');
    }

    public function test_authenticated_user_can_logout_via_post_legacy_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/logout');
        $response->assertRedirect('/admin/login');

        $this->assertGuest('web');
    }

    public function test_json_logout_returns_json_response(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/admin/logout');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => url('/admin/login'),
            ]);

        $this->assertGuest('web');
    }
}
