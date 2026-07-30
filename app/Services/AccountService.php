<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Models\BotLog;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

final class AccountService
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoAmfService $amfService,
        private readonly ZoneParserService $zoneParser,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Delete account and clean up authentication assets.
     */
    public function deleteAccount(Account $account): void
    {
        $cookieFile = $this->authService->getCookieFile($account);
        if (file_exists($cookieFile)) {
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
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

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
                'level' => 'success',
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
                'level' => 'error',
                'message' => '[Account] '.$errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }
    }

    /**
     * Update manually supplied session tokens.
     *
     * @param  array{dso_auth_token: string, dso_auth_user: string, bb_url: string}  $data
     */
    public function updateSession(Account $account, array $data): Account
    {
        $account->update([
            'dso_auth_token' => $data['dso_auth_token'],
            'dso_auth_user' => $data['dso_auth_user'],
            'bb_url' => $data['bb_url'],
            'status' => 'online',
        ]);

        $this->amfService->resetClient();

        BotLog::create([
            'account_id' => $account->id,
            'level' => 'success',
            'message' => '[Account] '.__('logs.account.session_updated'),
        ]);

        return $account;
    }

    /**
     * Fetch friend's zone data.
     *
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function getFriendZone(Account $account, int $friendId): array
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

        $zoneData = $account->zone_data ? json_decode($account->zone_data, true) : [];
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
        $cachedZone = $this->cache->get($cacheKey);

        if ($cachedZone) {
            $friendZoneData = json_decode((string) $cachedZone, true);
        } else {
            try {
                if (! $this->authService->isAuthenticated($account)) {
                    $this->authService->login($account);
                    $account->refresh();
                }

                Log::info("[FriendZone] Loading zone of friend #{$friendId} for account #{$account->id}");
                $rawAmf = $this->amfService->getZone($account, $friendId);
                $friendZoneData = $this->zoneParser->parse($rawAmf);

                $errorCode = $friendZoneData['errorCode'] ?? 0;
                if ($errorCode !== 0) {
                    $staleCache = $this->cache->get($staleCacheKey);
                    if ($staleCache) {
                        Log::warning("[FriendZone] Game server returned error {$errorCode} while loading zone of friend #{$friendId} for account #{$account->id}; falling back to stale cache");
                        $friendZoneData = json_decode((string) $staleCache, true);
                    } else {
                        return [
                            'status' => 500,
                            'payload' => [
                                'success' => false,
                                'message' => __('ui.account.friend_zone.server_error', [
                                    'code' => $errorCode,
                                    'error' => GameErrorResolver::getMessage((int) $errorCode),
                                ]),
                            ],
                        ];
                    }
                } else {
                    $jsonEncoded = json_encode($friendZoneData);
                    $this->cache->put($cacheKey, $jsonEncoded, 3600);
                    $this->cache->put($staleCacheKey, $jsonEncoded, 86400);
                }
            } catch (Exception $e) {
                Log::error("[FriendZone] Failed to load zone of friend #{$friendId} for account #{$account->id}: ".$e->getMessage());

                $staleCache = $this->cache->get($staleCacheKey);
                if ($staleCache) {
                    Log::warning("[FriendZone] Exception while loading zone of friend #{$friendId} for account #{$account->id}; falling back to stale cache");
                    $friendZoneData = json_decode((string) $staleCache, true);
                } else {
                    return [
                        'status' => 500,
                        'payload' => [
                            'success' => false,
                            'message' => __('ui.account.friend_zone.load_failed', ['message' => $e->getMessage()]),
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
