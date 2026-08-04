<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
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

    public function index(): SettingResource
    {
        return new SettingResource([
            'sync_interval' => (int) Setting::get('sync_interval', 30),
            'log_retention_days' => (int) Setting::get('log_retention_days', 30),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Setting::set('sync_interval', $validated['sync_interval']);
        Setting::set('log_retention_days', $validated['log_retention_days']);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated.',
            'settings' => new SettingResource($validated),
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
}
