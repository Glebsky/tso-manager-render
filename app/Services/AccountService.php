<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LogLevel;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Models\BotLog;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;

readonly class AccountService
{
    public function __construct(
        private TsoAuthService $authService,
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
        private CacheRepository $cache,
    ) {}

    /**
     * Delete account and clean up authentication assets.
     */
    public function deleteAccount(Account $account): void
    {
        $cookieFile = $this->authService->getCookieFile($account);
        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        $account->delete();
    }

    /**
     * Execute an action on an account.
     *
     * @param  array<string, mixed>  $params
     * @return array{success: bool, message: string}
     */
    public function executeAction(Account $account, string $actionType, array $params): array
    {
        try {
            $this->authService->ensureAuthenticated($account);

            switch ($actionType) {
                case 'stop_production':
                    $this->amfService->stopProduction($account, (int) ($params['grid'] ?? 0));
                    break;

                case 'start_production':
                    $this->amfService->startProduction($account, (int) ($params['grid'] ?? 0));
                    break;

                case 'apply_buff':
                    $result = $this->amfService->applyBuff(
                        $account,
                        (int) ($params['grid'] ?? 0),
                        (int) ($params['unique_id1'] ?? 0),
                        (int) ($params['unique_id2'] ?? 0)
                    );
                    $parsed = $this->zoneParser->parse($result);
                    $errorCode = $parsed['errorCode'] ?? 0;
                    if ($errorCode !== 0) {
                        $errorMsg = GameErrorResolver::getMessage((int) $errorCode);
                        throw new GameServerErrorException((int) $errorCode, $errorMsg);
                    }
                    break;

                case 'send_specialist':
                    $this->amfService->sendSpecialist(
                        $account,
                        (int) ($params['task_type'] ?? 0),
                        (int) ($params['sub_task_id'] ?? 0),
                        (int) ($params['unique_id1'] ?? 0),
                        (int) ($params['unique_id2'] ?? 0)
                    );
                    break;
            }

            $msg = __('logs.account.action_success', ['type' => $actionType]);

            BotLog::create([
                'account_id' => $account->id,
                'level' => LogLevel::Success,
                'message' => '[Account] '.$msg,
            ]);

            return [
                'success' => true,
                'message' => $msg,
            ];
        } catch (Exception $e) {
            $errorMsg = __('logs.account.action_failed', ['type' => $actionType, 'error' => $e->getMessage()]);

            BotLog::create([
                'account_id' => $account->id,
                'level' => LogLevel::Error,
                'message' => '[Account] '.$errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }
    }

    /**
     * Update manually supplied session tokens or account credentials.
     *
     * @param  array<string, string>  $data
     */
    public function updateSession(Account $account, array $data): Account
    {
        $updateFields = [];
        if (! empty($data['dso_auth_token'])) {
            $updateFields['dso_auth_token'] = $data['dso_auth_token'];
        }
        if (! empty($data['dso_auth_user'])) {
            $updateFields['dso_auth_user'] = $data['dso_auth_user'];
        }
        if (! empty($data['bb_url'])) {
            $updateFields['bb_url'] = $data['bb_url'];
        }
        if (! empty($data['password'])) {
            $updateFields['password'] = $data['password'];
        }

        if (! empty($updateFields)) {
            $updateFields['status'] = 'online';
            $account->update($updateFields);
        }

        $cookieFile = $this->authService->getCookieFile($account);
        if (! is_file($cookieFile)) {
            @file_put_contents($cookieFile, '');
        }

        $this->authService->resetSession($account);
        if (! empty($data['dso_auth_token'])) {
            Cache::forget("tso:session_invalidated:{$account->id}");
            $this->authService->markSessionVerified($account);
        }

        $this->amfService->invalidateSession($account->id);
        $this->amfService->resetClient();

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Success,
            'message' => '[Account] '.__('logs.account.session_updated'),
        ]);

        return $account;
    }

    public function clearCooldown(Account|int $account): void
    {
        $this->authService->clearCooldown($account);
    }

    /**
     * Fetch friend's zone data.
     *
     * @return array{status: int, payload: array<string, mixed>}
     *
     * @throws InvalidArgumentException
     */
    public function getFriendZone(Account $account, int $friendId, bool $force = false): array
    {
        if ($friendId <= 0) {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => __('ui.account.friend_zone.invalid_id'),
                ],
            ];
        }

        $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
        $friends = $zoneData['friends'] ?? [];
        $friend = null;

        foreach ($friends as $f) {
            if (isset($f['id']) && (int) $f['id'] === $friendId) {
                $friend = $f;
                break;
            }
        }

        if (! $friend) {
            return [
                'status' => 403,
                'payload' => [
                    'success' => false,
                    'message' => __('ui.account.friend_zone.not_in_friends_list'),
                ],
            ];
        }

        $cacheKey = "friend-zone:{$account->id}:{$friendId}";
        $staleCacheKey = "friend-zone-stale:{$account->id}:{$friendId}";

        if ($force) {
            $this->cache->forget($cacheKey);
            $this->cache->forget($staleCacheKey);
        }

        $cachedZone = $force ? null : $this->cache->get($cacheKey);

        if ($cachedZone) {
            $friendZoneData = json_decode((string) $cachedZone, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $maxAttempts = 3;
            $friendZoneData = null;
            $lastErrorCode = 0;
            $lastException = null;
            $reloggedIn = false;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $this->authService->ensureAuthenticated($account);

                    Log::info("[FriendZone] Loading zone of friend #{$friendId} for account #{$account->id} (attempt {$attempt}/{$maxAttempts})");
                    $rawAmf = $this->amfService->getZone($account, $friendId);
                    $parsed = $this->zoneParser->parse($rawAmf);

                    $errorCode = (int) ($parsed['errorCode'] ?? 0);
                    if ($errorCode === 0) {
                        $friendZoneData = $parsed;
                        $jsonEncoded = json_encode($friendZoneData, JSON_THROW_ON_ERROR);
                        $this->cache->put($cacheKey, $jsonEncoded, 3600);
                        $this->cache->put($staleCacheKey, $jsonEncoded, 86400);
                        break;
                    }

                    $lastErrorCode = $errorCode;
                    Log::warning("[FriendZone] Game server returned error {$errorCode} while loading zone of friend #{$friendId} for account #{$account->id} (attempt {$attempt}/{$maxAttempts})");

                    if ($errorCode === 1012 && $attempt < $maxAttempts) {
                        Log::info("[FriendZone] Zone busy (error 1012) for friend #{$friendId}; sleeping before retry");
                        sleep(2);

                        continue;
                    }

                    break;
                } catch (Exception $e) {
                    $lastException = $e;
                    Log::error("[FriendZone] Exception while loading zone of friend #{$friendId} for account #{$account->id} (attempt {$attempt}/{$maxAttempts}): ".$e->getMessage());

                    if ($attempt < $maxAttempts) {
                        // FR-009: the AMF layer no longer re-logs in by itself —
                        // one forced re-login here, never more per request.
                        if ($e instanceof SessionExpiredException && ! $reloggedIn) {
                            Log::info("[FriendZone] Session rejected for account #{$account->id}; performing the single forced re-login");
                            $this->authService->resetSession($account);
                            $this->amfService->invalidateSession($account->id);
                            $this->amfService->resetClient($account->id);
                            $this->authService->ensureAuthenticated($account, force: true);
                            $account->refresh();
                            $reloggedIn = true;
                        }

                        sleep(2);

                        continue;
                    }
                }
            }

            if ($friendZoneData === null) {
                $staleCache = $this->cache->get($staleCacheKey);
                if ($staleCache && ! $force) {
                    Log::warning("[FriendZone] Falling back to stale cache for friend #{$friendId} for account #{$account->id}");
                    $friendZoneData = json_decode((string) $staleCache, true, 512, JSON_THROW_ON_ERROR);
                } else {
                    if ($lastErrorCode !== 0) {
                        return [
                            'status' => 500,
                            'payload' => [
                                'success' => false,
                                'message' => __('ui.account.friend_zone.server_error', [
                                    'code' => $lastErrorCode,
                                    'error' => GameErrorResolver::getMessage($lastErrorCode),
                                ]),
                            ],
                        ];
                    }

                    return [
                        'status' => 500,
                        'payload' => [
                            'success' => false,
                            'message' => __('ui.account.friend_zone.load_failed', [
                                'message' => $lastException ? $lastException->getMessage() : 'Unknown error',
                            ]),
                        ],
                    ];
                }
            }
        }

        $buildings = [];
        foreach ($friendZoneData['buildings'] ?? [] as $b) {
            $buildings[] = [
                'buildingName_string' => $b['buildingName_string'] ?? $b['buildingName'] ?? 'Building',
                'buildingName' => $b['buildingName'] ?? $b['buildingName_string'] ?? 'Building',
                'buildingGrid' => $b['buildingGrid'] ?? $b['grid'] ?? 0,
                'upgradeLevel' => $b['upgradeLevel'] ?? $b['level'] ?? 1,
                'isProductionActive' => $b['isProductionActive'] ?? false,
                'buildingMode' => $b['buildingMode'] ?? 0,
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'friend' => [
                    'id' => $friendId,
                    'username' => $friend['username'] ?? $friend['nickname'] ?? 'Unknown',
                ],
                'buildings' => $buildings,
            ],
        ];
    }
}
