<?php

namespace App\Http\Controllers;

use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Models\Setting;
use Illuminate\Http\Request;

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

    private function loadSettings(): array
    {
        return [
            'sync_interval' => (int) Setting::get('sync_interval', 30),
            'log_retention_days' => (int) Setting::get('log_retention_days', 30),
            'timezone' => (string) Setting::get('timezone', 'UTC'),
        ];
    }

    private function saveSettings(array $settings): void
    {
        Setting::set('sync_interval', $settings['sync_interval']);
        Setting::set('log_retention_days', $settings['log_retention_days']);
        Setting::set('timezone', $settings['timezone']);
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
