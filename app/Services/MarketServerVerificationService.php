<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\MarketServerConnection;
use Illuminate\Support\Str;

class MarketServerVerificationService
{
    /**
     * Determine server and locale from account properties.
     */
    public function detectServerForAccount(Account $account): array
    {
        $region = strtolower((string) ($account->region ?? ''));
        if (empty($region)) {
            return [
                'detected_server_id' => null,
                'detected_locale' => null,
                'game_world' => null,
                'confidence' => 'none',
            ];
        }

        $serverConfig = TsoAuthService::getServerConfig($region);
        $locale = match ($region) {
            'ru' => 'RU',
            'de' => 'DE',
            'en', 'us' => 'EN',
            'fr' => 'FR',
            'pl' => 'PL',
            'es', 'es2' => 'ES',
            'nl' => 'NL',
            'cz' => 'CZ',
            'pt' => 'PT',
            'it' => 'IT',
            'el' => 'EL',
            'ro' => 'RO',
            default => strtoupper($region),
        };

        $gameWorld = trim((string) $account->server_name);
        $serverId = $region;

        if (! empty($gameWorld)) {
            $worldSlug = Str::slug($gameWorld, '_');
            if (empty($worldSlug)) {
                $worldSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9_]+/', '', $gameWorld));
            }
            if (! empty($worldSlug)) {
                $serverId = "{$region}_{$worldSlug}";
            }
        }

        return [
            'detected_server_id' => $serverId,
            'detected_locale' => $locale,
            'game_world' => $gameWorld ?: null,
            'confidence' => $serverConfig !== null ? 'high' : 'medium',
        ];
    }

    /**
     * Verify if the account matches the target server connection.
     */
    public function verifyAccountServerMatch(Account $account, MarketServerConnection $connection): array
    {
        $detection = $this->detectServerForAccount($account);
        $detectedServerId = $detection['detected_server_id'];
        $targetServerId = strtolower($connection->server_id);
        $targetLocale = strtoupper($connection->locale);

        if (! $detectedServerId) {
            return [
                'status' => 'unverified',
                'detected_server' => null,
                'message' => __('ui.market.verification_unverified'),
            ];
        }

        $detectedRegion = strtolower((string) $account->region);
        $targetRegion = explode('_', $targetServerId)[0];

        $isMatch = ($detectedServerId === $targetServerId)
            || ($detectedRegion === $targetRegion && $detection['detected_locale'] === $targetLocale);

        if ($isMatch) {
            return [
                'status' => 'verified',
                'detected_server' => $detectedServerId,
                'detected_locale' => $detection['detected_locale'],
                'message' => __('ui.market.verification_verified'),
            ];
        }

        return [
            'status' => 'mismatch',
            'detected_server' => $detectedServerId,
            'detected_locale' => $detection['detected_locale'],
            'message' => __('ui.market.verification_mismatch', ['detected' => $detectedServerId, 'server' => $connection->server_id]),
        ];
    }
}
