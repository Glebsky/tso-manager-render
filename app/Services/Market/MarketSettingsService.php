<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Models\Setting;

/**
 * Reads and writes the market synchronization settings.
 *
 * The "is it custom? then read the other key" resolution used to be inlined in
 * both getServers() and getBulk(); it now exists once.
 */
final class MarketSettingsService
{
    private const string KEY_INTERVAL = 'market_sync_interval';

    private const string KEY_CUSTOM_MINUTES = 'market_custom_interval_minutes';

    public function syncInterval(): string
    {
        return (string) Setting::get(self::KEY_INTERVAL, (string) $this->defaultMinutes());
    }

    public function customIntervalMinutes(): int
    {
        return (int) Setting::get(self::KEY_CUSTOM_MINUTES, $this->defaultMinutes());
    }

    /**
     * Effective interval in minutes, resolving the "custom" option.
     */
    public function syncIntervalMinutes(): int
    {
        $interval = $this->syncInterval();

        return $interval === 'custom' ? $this->customIntervalMinutes() : (int) $interval;
    }

    public function update(string $syncInterval, ?int $customIntervalMinutes): void
    {
        Setting::set(self::KEY_INTERVAL, $syncInterval);
        Setting::set(self::KEY_CUSTOM_MINUTES, $customIntervalMinutes);
    }

    private function defaultMinutes(): int
    {
        return (int) config('market.sync.default_interval_minutes', 15);
    }
}
