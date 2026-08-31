<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerTelescope();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        DB::whenQueryingForLongerThan(200, function ($connection, QueryExecuted $event): void {
            Log::warning('[Slow query]', [
                'sql'  => $event->sql,
                'time' => $event->time,
            ]);
        });

        $this->setRateLimiter();

        $this->bootLoggingContext();
    }

    private function bootLoggingContext(): void
    {
        Queue::createPayloadUsing(function () {
            return [
                'request_id' => Log::sharedContext()['request_id'] ?? (string) Str::uuid(),
            ];
        });

        Queue::before(function (JobProcessing $event) {
            $payload = $event->job->payload();
            $requestId = $payload['request_id'] ?? (string) Str::uuid();

            Log::shareContext(['request_id' => $requestId]);
        });

        Event::listen(CommandStarting::class, function () {
            Log::shareContext([
                'request_id' => (string) Str::uuid(),
            ]);
        });
    }

    private function setRateLimiter(): void
    {
        RateLimiter::for('public-market', static function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip() ?: 'global');
        });

        RateLimiter::for('api-actions', static function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip() ?: 'global'));
        });
    }

    private function registerTelescope(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}
