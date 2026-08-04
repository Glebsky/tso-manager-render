<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\SystemLogCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller for application-level configuration settings.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemLogCleanupService $logCleanupService,
    ) {}

    public function index(): JsonResponse
    {
        $settings = $this->loadSettings();
        $settings['server_time'] = now()->toIso8601String();

        return response()->json($settings);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->saveSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated.',
            'settings' => $validated,
        ]);
    }

    public function clearLogs(): JsonResponse
    {
        $this->logCleanupService->clearAllLogs();

        return response()->json([
            'success' => true,
            'message' => 'All logs cleared.',
        ]);
    }

    public function stopAllTasks(): JsonResponse
    {
        $affected = ScheduledTask::query()->where('is_active', true)->pluck('id')->all();

        Log::warning(sprintf(
            '[Task] Mass pause via /settings/tasks/stop: %d task(s) deactivated [%s]',
            count($affected),
            implode(', ', array_map(static fn ($id) => '#'.$id, $affected))
        ));

        ScheduledTask::query()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'All scheduled tasks paused.',
        ]);
    }

    private function loadSettings(): array
    {
        return [
            'sync_interval' => (int) Setting::get('sync_interval', 30),
            'log_retention_days' => (int) Setting::get('log_retention_days', 30),
        ];
    }

    private function saveSettings(array $settings): void
    {
        Setting::set('sync_interval', $settings['sync_interval']);
        Setting::set('log_retention_days', $settings['log_retention_days']);
    }
}
