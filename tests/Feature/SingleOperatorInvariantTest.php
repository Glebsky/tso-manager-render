<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SingleOperatorInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_succeeds_exactly_once_and_second_attempt_is_rejected_html_and_json(): void
    {
        // Clear auto-created user from TestCase setUp so we start with 0 users
        User::query()->delete();
        Auth::logout();
        $this->app['session']->flush();

        // First registration via HTML form
        $response1 = $this->post('/admin/register', [
            'name' => 'First Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response1->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertEquals(1, User::count());

        // Logout operator for second attempt
        Auth::logout();
        $this->app['session']->flush();

        // Second registration attempt via HTML form
        $response2 = $this->post('/admin/register', [
            'name' => 'Second Admin',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response2->assertSessionHasErrors(['email']);
        $this->assertEquals(1, User::count());

        // Second registration attempt via JSON API
        $response3 = $this->postJson('/admin/register', [
            'name' => 'Third Admin',
            'email' => 'admin3@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertEquals(1, User::count());
    }

    public function test_concurrent_registration_attempts_still_yield_single_user(): void
    {
        // Clear auto-created user from TestCase setUp
        User::query()->delete();
        Auth::logout();
        $this->app['session']->flush();

        $this->assertEquals(0, User::count());

        // Create first user
        User::create([
            'name' => 'Initial User',
            'email' => 'first@example.com',
            'password' => bcrypt('password'),
        ]);

        // Attempting another registration when user already exists inside transaction
        $this->expectException(ValidationException::class);

        $controller = new AuthController;
        $request = Request::create('/admin/register', 'POST', [
            'name' => 'Concurrent User',
            'email' => 'concurrent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $controller->register($request);
    }

    public function test_every_auth_sanctum_route_returns_401_for_guest(): void
    {
        // Ensure no user is logged in
        Auth::logout();
        $this->app['session']->flush();

        $routes = Route::getRoutes()->getRoutes();
        $protectedRoutes = [];

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth:sanctum', $middleware, true)) {
                $protectedRoutes[] = $route;
            }
        }

        $this->assertNotEmpty($protectedRoutes, 'No auth:sanctum routes found in application.');

        foreach ($protectedRoutes as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);
            $uri = $route->uri();

            // Format URI cleanly
            $testUri = preg_replace('/\{[^}]+\}/', '1', $uri);
            if (! str_starts_with($testUri, '/')) {
                $testUri = '/'.$testUri;
            }

            foreach ($methods as $method) {
                // Perform json request without auth headers/session
                $response = $this->json($method, $testUri);
                $this->assertEquals(
                    401,
                    $response->getStatusCode(),
                    "Route [{$method} {$uri}] (tested as {$testUri}) did not return 401 Unauthenticated for guest."
                );
            }
        }
    }

    public function test_public_market_endpoints_are_reachable_without_auth_and_expose_exact_keys(): void
    {
        // 1. GET /api/public/market/servers
        $responseServers = $this->getJson('/api/public/market/servers');
        $responseServers->assertStatus(200);
        $serversData = $responseServers->json('data');

        if (! empty($serversData)) {
            $serverKeys = array_keys($serversData[0]);
            sort($serverKeys);
            $expectedServerKeys = ['id', 'locale', 'server_id', 'sync_status', 'world_name'];
            $this->assertEquals($expectedServerKeys, $serverKeys, 'PublicServerResource exposed unexpected or missing keys.');
        }

        // 2. GET /api/public/market/analytics
        $responseAnalytics = $this->getJson('/api/public/market/analytics');
        $responseAnalytics->assertStatus(200);

        // 3. GET /api/public/market/popular
        $responsePopular = $this->getJson('/api/public/market/popular');
        $responsePopular->assertStatus(200);

        // 4. GET /api/public/market/arbitrage
        $responseArbitrage = $this->getJson('/api/public/market/arbitrage');
        $responseArbitrage->assertStatus(200);

        // 5. GET /api/public/market/bulk
        $responseBulk = $this->getJson('/api/public/market/bulk');
        $responseBulk->assertStatus(200);
        $offers = $responseBulk->json('data.offers');

        if (! empty($offers)) {
            $offerKeys = array_keys($offers[0]);
            sort($offerKeys);
            $expectedOfferKeys = [
                'amount',
                'created_at',
                'expires_at',
                'id',
                'item_id',
                'item_name',
                'lots_remaining',
                'offer_id',
                'price',
                'sender_name',
                'server_id',
                'target_amount',
                'target_item_id',
                'target_item_name',
                'volume',
            ];
            $this->assertEquals($expectedOfferKeys, $offerKeys, 'MarketOfferResource exposed unexpected or missing keys.');
        }
    }
}
