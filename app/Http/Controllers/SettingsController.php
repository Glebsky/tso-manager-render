<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\Market\MarketSettingsService;
use App\Services\SystemLogCleanupService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Controller for application-level configuration settings.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemLogCleanupService $logCleanupService,
        private readonly MarketSettingsService $marketSettings,
    ) {}

    public function index(): SettingResource
    {
        return new SettingResource([
            'sync_interval' => (int) Setting::get('sync_interval', 30),
            'log_retention_days' => (int) Setting::get('log_retention_days', 30),
            'combat_simulator_url' => $this->marketSettings->combatSimulatorUrl(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): SettingResource
    {
        $validated = $request->validated();

        Setting::set('sync_interval', $validated['sync_interval']);
        Setting::set('log_retention_days', $validated['log_retention_days']);

        if (array_key_exists('combat_simulator_url', $validated)) {
            $this->marketSettings->updateCombatSimulatorUrl($validated['combat_simulator_url']);
        }

        $validated['combat_simulator_url'] = $this->marketSettings->combatSimulatorUrl();

        return new SettingResource($validated);
    }

    public function clearLogs(): Response
    {
        $this->logCleanupService->clearAllLogs();

        return response()->noContent();
    }

    public function stopAllTasks(): Response
    {
        $affected = ScheduledTask::query()->where('is_active', true)->pluck('id')->all();

        Log::warning(sprintf(
            '[Task] Mass pause via /settings/tasks/stop: %d task(s) deactivated [%s]',
            count($affected),
            implode(', ', array_map(static fn ($id) => '#'.$id, $affected))
        ));

        ScheduledTask::query()->update(['is_active' => false]);

        return response()->noContent();
    }
}
