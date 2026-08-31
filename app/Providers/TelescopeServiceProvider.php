<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if (! $this->app->environment('local')) {
            return;
        }

        Queue::after(static function (JobProcessed $event): void {
            $uuid = $event->job->payload()['telescope_uuid'] ?? null;
            if (! $uuid) {
                return;
            }

            try {
                $entry = DB::table('telescope_entries')->where('uuid', $uuid)->first();
                if ($entry) {
                    $content = json_decode($entry->content, true);
                    if (is_array($content) && ($content['status'] ?? '') === 'pending') {
                        $content['status'] = 'processed';
                        $content['exception'] = null;
                        DB::table('telescope_entries')
                            ->where('uuid', $uuid)
                            ->update([
                                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            } catch (\Throwable) {
                // Ignore any logging errors in local worker
            }
        });

        Queue::failing(static function (JobFailed $event): void {
            $uuid = $event->job->payload()['telescope_uuid'] ?? null;
            if (! $uuid) {
                return;
            }

            try {
                $entry = DB::table('telescope_entries')->where('uuid', $uuid)->first();
                if ($entry) {
                    $content = json_decode($entry->content, true);
                    if (is_array($content)) {
                        $content['status'] = 'failed';
                        $content['exception'] = [
                            'message' => $event->exception->getMessage(),
                            'file' => $event->exception->getFile(),
                            'line' => $event->exception->getLine(),
                        ];
                        DB::table('telescope_entries')
                            ->where('uuid', $uuid)
                            ->update([
                                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            } catch (\Throwable) {
                // Ignore any logging errors in local worker
            }
        });

        Queue::exceptionOccurred(static function (JobExceptionOccurred $event): void {
            $uuid = $event->job->payload()['telescope_uuid'] ?? null;
            if (! $uuid) {
                return;
            }

            try {
                $entry = DB::table('telescope_entries')->where('uuid', $uuid)->first();
                if ($entry) {
                    $content = json_decode($entry->content, true);
                    if (is_array($content)) {
                        $content['exception'] = [
                            'message' => $event->exception->getMessage(),
                            'file' => $event->exception->getFile(),
                            'line' => $event->exception->getLine(),
                        ];
                        DB::table('telescope_entries')
                            ->where('uuid', $uuid)
                            ->update([
                                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            } catch (\Throwable) {
                // Ignore any logging errors in local worker
            }
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            $allowed = array_filter(array_map('trim', explode(',', (string) config('telescope.allowed_emails', ''))));

            return in_array($user->email, $allowed, true);
        });
    }
}
