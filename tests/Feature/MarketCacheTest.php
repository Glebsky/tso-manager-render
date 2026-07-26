<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MarketCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketCacheTest extends TestCase
{
    use RefreshDatabase;

    private MarketCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = app(MarketCacheService::class);
        Cache::flush();
    }

    public function test_data_version_increments_correctly(): void
    {
        $this->assertSame(1, $this->cacheService->dataVersion('ru'));

        $newVersion = $this->cacheService->bumpDataVersion('ru');
        $this->assertSame(2, $newVersion);
        $this->assertSame(2, $this->cacheService->dataVersion('ru'));
    }

    public function test_remember_uses_data_version_and_canonical_params(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return ['foo' => 'bar'];
        };

        // First call populates cache
        $res1 = $this->cacheService->remember('ru', 'goods', ['b' => 2, 'a' => 1], 300, $callback);
        $this->assertSame(1, $calls);
        $this->assertSame(['foo' => 'bar'], $res1);

        // Different param order gets canonicalized to same key
        $res2 = $this->cacheService->remember('ru', 'goods', ['a' => 1, 'b' => 2], 300, $callback);
        $this->assertSame(1, $calls);
        $this->assertSame(['foo' => 'bar'], $res2);

        // Bump version invalidates cache key
        $this->cacheService->bumpDataVersion('ru');
        $res3 = $this->cacheService->remember('ru', 'goods', ['a' => 1, 'b' => 2], 300, $callback);
        $this->assertSame(2, $calls);
        $this->assertSame(['foo' => 'bar'], $res3);
    }

    public function test_public_market_api_returns_etag_and_304(): void
    {
        $response1 = $this->getJson('/api/public/market/servers');
        $response1->assertStatus(200);
        $response1->assertHeader('Cache-Control', 'max-age=60, public, stale-while-revalidate=300');
        $this->assertTrue($response1->headers->has('ETag'));
        $this->assertTrue($response1->headers->has('X-Data-Version'));

        $etag = $response1->headers->get('ETag');

        // Sending same Etag returns 304 Not Modified
        $response2 = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/public/market/servers');
        $response2->assertStatus(304);
    }

    public function test_setting_cache_and_invalidation(): void
    {
        Setting::set('test_key', 'initial_val');
        $this->assertSame('initial_val', Setting::get('test_key'));

        // Direct DB update (bypassing model) to prove Setting::get is cached
        Setting::where('key', 'test_key')->update(['value' => 'db_changed_val']);
        $this->assertSame('initial_val', Setting::get('test_key'));

        // Setting::set invalidates cache
        Setting::set('test_key', 'new_val');
        $this->assertSame('new_val', Setting::get('test_key'));
    }

    public function test_bump_data_version_forgets_old_keys(): void
    {
        $callback = fn () => ['data' => 'test'];

        $this->cacheService->remember('ru', 'goods', [], 300, $callback);
        $keysBefore = Cache::get('market:keys:ru', []);
        $this->assertCount(1, $keysBefore);
        $oldKey = $keysBefore[0];

        $this->assertTrue(Cache::has($oldKey));

        // Bumping data version invalidates and forgets the key explicitly
        $this->cacheService->bumpDataVersion('ru');

        $this->assertFalse(Cache::has($oldKey));
        $this->assertEmpty(Cache::get('market:keys:ru', []));
    }
}
