<?php

namespace App\Http\Controllers;

use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    const TIMEZONES = [
        'UTC',
        'Europe/Moscow',
        'Europe/Kiev',
        'Europe/London',
        'Europe/Berlin',
        'Europe/Paris',
        'Europe/Rome',
        'Europe/Madrid',
        'Europe/Warsaw',
        'Europe/Bucharest',
        'Europe/Athens',
        'Europe/Istanbul',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Sao_Paulo',
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Asia/Seoul',
        'Asia/Kolkata',
        'Asia/Dubai',
        'Australia/Sydney',
        'Pacific/Auckland',
    ];

    private function getSettingsPath(): string
    {
        return 'settings.json';
    }

    private function loadSettings(): array
    {
        if (Storage::disk('local')->exists($this->getSettingsPath())) {
            return json_decode(Storage::disk('local')->get($this->getSettingsPath()), true) ?? [];
        }

        return [
            'sync_interval' => 30,
            'log_retention_days' => 30,
            'timezone' => 'UTC',
        ];
    }

    private function saveSettings(array $settings): void
    {
        Storage::disk('local')->put($this->getSettingsPath(), json_encode($settings, JSON_PRETTY_PRINT));
    }

    public function index()
    {
        $settings = $this->loadSettings();
        $settings['server_time'] = now()->toIso8601String();

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'sync_interval' => 'required|integer|in:0,5,15,30,60',
            'log_retention_days' => 'required|integer|in:0,7,14,30,90',
            'timezone' => 'required|string|in:'.implode(',', self::TIMEZONES),
        ]);

        $this->saveSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated.',
            'settings' => $validated,
        ]);
    }

    public function clearLogs()
    {
        BotLog::truncate();

        return response()->json([
            'success' => true,
            'message' => 'All logs cleared.',
        ]);
    }

    public function stopAllTasks()
    {
        ScheduledTask::query()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'All scheduled tasks paused.',
        ]);
    }
}
