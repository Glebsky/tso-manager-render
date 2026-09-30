<?php

declare(strict_types=1);

namespace App\Services\Amf\Transport;

use App\Exceptions\SessionBusyException;
use App\Models\Account;
use Exception;

interface TsoClientInterface
{
    /**
     * Resolve the target AMF server endpoint URL for an account and zone.
     */
    public function resolveServerUrl(Account $account, int $targetZoneId = 0, ?string &$dsId = null): string;

    /**
     * Send an AMF command to the remote game server.
     *
     * Implementations MUST serialize commands per account (INV-004) and MUST
     * NOT send when the account's session lock cannot be acquired.
     *
     * @throws SessionBusyException the account's session is in use by another process (retryable, no re-login)
     * @throws Exception transport / session errors
     */
    public function sendCommand(Account $account, mixed $dServerCall, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string;

    /**
     * Clear cached client instances in memory.
     *
     * @param  int|null  $accountId  Clear only this account's entries; null clears everything.
     */
    public function resetClients(?int $accountId = null): void;

    /**
     * Invalidate shared session in cache across all processes for a specific account.
     */
    public function invalidateSession(int $accountId): void;

    /**
     * Set active DSId session identifier.
     */
    public function setDsId(string $dsId, ?int $accountId = null): void;
}
