<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Market\Arbitrage\LoopArbitrageFinder;
use App\Services\Market\Contracts\ArbitrageFinder;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\MarketOfferQueryService;
use App\Services\Market\Support\TimeBucket\MySqlTimeBucketExpression;
use App\Services\Market\Support\TimeBucket\PostgresTimeBucketExpression;
use App\Services\Market\Support\TimeBucket\SqliteTimeBucketExpression;
use App\Services\Market\Support\TimeBucket\TimeBucketExpressionFactory;
use App\Services\Market\Tradeables\CompositeTradeableNameResolver;
use App\Services\MarketCacheService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Composition root of the market domain.
 *
 * Every abstraction the market services depend on is bound here, so no
 * consumer ever news up a concrete collaborator or reads configuration on its
 * own (Dependency Inversion). Swapping an implementation - a different name
 * resolver, another arbitrage algorithm, an extra database driver - is a
 * one-line change in this file.
 */
final class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarketCacheService::class);
        $this->app->bind(ResourceNameResolver::class, CompositeTradeableNameResolver::class);

        $this->app->singleton(TimeBucketExpressionFactory::class, static function (): TimeBucketExpressionFactory {
            $sqlite = new SqliteTimeBucketExpression;

            return new TimeBucketExpressionFactory(
                [
                    new PostgresTimeBucketExpression,
                    new MySqlTimeBucketExpression,
                    $sqlite,
                ],
                $sqlite,
            );
        });

        $this->app->singleton(MarketOfferQueryService::class, static function (Application $app): MarketOfferQueryService {
            return new MarketOfferQueryService(
                (int) $app->make('config')->get('market.offer_lifetime_hours', 6),
            );
        });

        $this->app->bind(ArbitrageFinder::class, static function (Application $app): ArbitrageFinder {
            return new LoopArbitrageFinder(
                $app->make(ResourceNameResolver::class),
                $app->make(MarketOfferQueryService::class),
                (bool) $app->make('config')->get('market.arbitrage.enable_three_step_loops', true),
            );
        });
    }
}
