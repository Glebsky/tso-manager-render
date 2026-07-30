<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Services\Amf\Amf3Encoder;
use Exception;
use Illuminate\Support\Facades\Log;

// ── VO Classes ──────────────────────────────────────────────────────────────────

class defaultGame_Communication_VO_dServerCall
{
    public $dsoAuthToken;

    public $type;

    public $zoneID;

    public $dsoAuthUser;

    public $data;

    public $dsoAuthRandomClientID;
}

class defaultGame_Communication_VO_dServerAction
{
    public $endGrid;

    public $grid;

    public $data;

    public $type;
}

class flex_messaging_messages_RemotingMessage
{
    public $destination;

    public $operation;

    public $source;

    public $timestamp = 0;

    public $timeToLive = 0;

    public $messageId;

    public $clientId = null;

    public $headers;

    public $body;
}

// ── AMF0 Envelope wrapper ───────────────────────────────────────────────────────

function wrapAmf0Remoting(string $targetUri, string $responseUri, string $amf3Body): string
{
    $out = '';
    $out .= pack('n', 3);
    $out .= pack('n', 0);
    $out .= pack('n', 1);
    $out .= pack('n', strlen($targetUri)).$targetUri;
    $out .= pack('n', strlen($responseUri)).$responseUri;
    $out .= pack('N', 0xFFFFFFFF);
    $out .= chr(0x11);
    $out .= $amf3Body;

    return $out;
}

// ── Load-balancer resolver ──────────────────────────────────────────────────────

function getRealAmfUrl(string $bbUrl, string $dsoAuthUser, string $dsoAuthToken, string $cookieFile, int $targetZoneId = 0, ?string &$dsId = null): string
{
    $lsUrl = $bbUrl;
    $amfServerUrl = '';

    Log::info("[TsoAmf] Resolving real AMF server: bbUrl={$lsUrl}, user={$dsoAuthUser}, targetZoneId={$targetZoneId}");

    $authUrl = rtrim($lsUrl, '/').'/authenticate';
    $chAuth = curl_init();
    curl_setopt($chAuth, CURLOPT_URL, $authUrl);
    curl_setopt($chAuth, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chAuth, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chAuth, CURLOPT_POST, true);
    curl_setopt($chAuth, CURLOPT_POSTFIELDS, http_build_query([
        'DSOAUTHUSER' => $dsoAuthUser,
        'DSOAUTHTOKEN' => $dsoAuthToken,
    ]));
    curl_setopt($chAuth, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($chAuth, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($chAuth, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
        'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
    ]);
    $authRes = (string) curl_exec($chAuth);
    $authStatus = (int) curl_getinfo($chAuth, CURLINFO_HTTP_CODE);
    curl_close($chAuth);

    Log::info("[TsoAmf] Load server authentication: HTTP {$authStatus}, response: ".trim($authRes));

    if ($authStatus === 200 && ! empty($authRes)) {
        $parts = explode('|', trim($authRes));
        if (count($parts) >= 3) {
            $hash = trim($parts[2]);
            if (strlen($hash) === 32) {
                $dsId = substr($hash, 0, 8).'-'.
                        substr($hash, 8, 4).'-'.
                        substr($hash, 12, 4).'-'.
                        substr($hash, 16, 4).'-'.
                        substr($hash, 20);
                Log::info("[TsoAmf] Extracted DSId from authentication response: {$dsId}");
            }
        }
    }

    $maxRetries = 20;
    $lsStatus = 0;
    $lsRes = '';

    for ($i = 0; $i < $maxRetries; $i++) {
        $ch = curl_init();
        $requestUrl = rtrim($lsUrl, '/').'/Z'.(int) round(microtime(true) * 1000);
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);

        $data = http_build_query([
            'zoneID' => $targetZoneId,
            'DSOAUTHTOKEN' => $dsoAuthToken,
            'DSOAUTHUSER' => $dsoAuthUser,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
            'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
        ]);

        $lsRes = (string) curl_exec($ch);
        $lsStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info("[TsoAmf] Load server attempt {$i}: HTTP {$lsStatus}, URL {$requestUrl}, response: ".substr($lsRes, 0, 300));

        if ($lsStatus !== 202) {
            $amfServerUrl = str_replace(':123443', '', trim($lsRes));
            break;
        }
        sleep(2);
    }

    if (empty($amfServerUrl)) {
        throw new Exception("Timeout waiting for Load Server. Last status: {$lsStatus}, Resp: {$lsRes}");
    }

    if (! str_starts_with($amfServerUrl, 'http')) {
        if (str_starts_with($amfServerUrl, '//')) {
            $amfServerUrl = 'https:'.$amfServerUrl;
        } else {
            $amfServerUrl = rtrim($lsUrl, '/').$amfServerUrl;
        }
    }

    Log::info("[TsoAmf] Real AMF server resolved: {$amfServerUrl}");

    return $amfServerUrl;
}

// ── AMF Client ──────────────────────────────────────────────────────────────────

class TsoAmfClient
{
    private string $dsId = 'nil';

    public function __construct(
        private readonly string $serverUrl,
        private readonly string $cookieFile
    ) {}

    public function setDsId(string $dsId): void
    {
        $this->dsId = $dsId;
    }

    public function sendCommand(mixed $dServerCall, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler'): string
    {
        $message = new flex_messaging_messages_RemotingMessage;
        $message->destination = $destination;
        $message->operation = $operation;
        $message->source = $source;
        $message->messageId = sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535), mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
        );
        $message->headers = new \stdClass;
        $message->headers->DSId = $this->dsId;
        $message->headers->DSEndpoint = 'SMC-Endpoint';
        $message->body = [$dServerCall];

        $encoder = new Amf3Encoder;
        $encoder->encode([$message]);
        $amf3Body = $encoder->getOutput();

        $amf0Envelope = wrapAmf0Remoting('null', '/1', $amf3Body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-amf',
            'Accept: */*',
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
            'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
            'x-flash-version: 11,4,402,287',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $amf0Envelope);

        $response = (string) curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new Exception('AMF cURL Error: '.$error);
        }
        if ($httpCode !== 200) {
            throw new Exception("AMF Server returned HTTP {$httpCode}. Response: ".$response);
        }

        return $response;
    }
}

// ── Main Service ────────────────────────────────────────────────────────────────

class TsoAmfService
{
    public const CMD_BUILD = 50;

    public const CMD_UPGRADE = 60;

    public const CMD_APPLY_BUFF = 61;

    public const CMD_SET_TASK = 95;

    public const CMD_STOP_PRODUCTION = 107;

    public const CMD_GET_ZONE = 1001;

    /** @var array<string, TsoAmfClient> */
    private array $clients = [];

    private string $dsId = 'nil';

    private int $dsoAuthRandomClientID;

    public function __construct(private readonly TsoAuthService $authService)
    {
        $this->dsoAuthRandomClientID = mt_rand(0, 2147483646);
    }

    public function setDsId(string $dsId): void
    {
        $this->dsId = $dsId;
        foreach ($this->clients as $client) {
            $client->setDsId($dsId);
        }
    }

    private function buildServerCall(Account $account, int $type, mixed $actionData, ?int $targetZoneId = null): defaultGame_Communication_VO_dServerCall
    {
        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = $account->dso_auth_token;
        $call->dsoAuthUser = (int) $account->dso_auth_user;
        $call->zoneID = $targetZoneId ?? (int) $account->dso_auth_user;
        $call->type = $type;
        $call->dsoAuthRandomClientID = $this->dsoAuthRandomClientID;
        $call->data = $actionData;

        return $call;
    }

    private function buildServerAction(int $type, int $grid, int $endGrid, mixed $data = null): defaultGame_Communication_VO_dServerAction
    {
        $action = new defaultGame_Communication_VO_dServerAction;
        $action->type = $type;
        $action->grid = $grid;
        $action->endGrid = $endGrid;
        $action->data = $data;

        return $action;
    }

    private function getClient(Account $account, int $targetZoneId = 0): TsoAmfClient
    {
        $clientKey = $account->id.':'.$targetZoneId;
        if (! isset($this->clients[$clientKey])) {
            $cookieFile = $this->authService->getCookieFile($account);
            $dsId = 'nil';
            $amfServerUrl = getRealAmfUrl(
                (string) $account->bb_url,
                (string) $account->dso_auth_user,
                (string) $account->dso_auth_token,
                $cookieFile,
                $targetZoneId,
                $dsId
            );

            $this->clients[$clientKey] = new TsoAmfClient($amfServerUrl, $cookieFile);
            $this->clients[$clientKey]->setDsId($dsId);
        }

        return $this->clients[$clientKey];
    }

    public function resetClient(): void
    {
        $this->clients = [];
    }

    private function sendServerCall(Account $account, int $commandType, mixed $actionData, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
    {
        $zoneId = $targetZoneId ?? 0;

        try {
            $client = $this->getClient($account, $zoneId);
            $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

            return $client->sendCommand($call, $destination, $operation, $source);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            if (str_contains($errorMsg, 'Load Server') || str_contains($errorMsg, '301') || str_contains($errorMsg, 'HTTP 500') || str_contains($errorMsg, 'HTTP 401') || str_contains($errorMsg, 'HTTP 403')) {
                try {
                    $this->authService->login($account);
                    $account->refresh();
                    $this->resetClient();

                    $client = $this->getClient($account, $zoneId);
                    $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

                    return $client->sendCommand($call, $destination, $operation, $source);
                } catch (Exception $retryException) {
                    throw new Exception($e->getMessage().' (Auto-relogin also failed: '.$retryException->getMessage().')');
                }
            }

            throw $e;
        }
    }

    public function getZone(Account $account, ?int $targetZoneId = null): string
    {
        $zoneId = $targetZoneId ?? (int) $account->dso_auth_user;

        return $this->sendServerCall(
            $account,
            self::CMD_GET_ZONE,
            false,
            'SMC',
            'ExecuteServerCall',
            'com.bluebyte.game.servlet.EventHandler',
            $zoneId
        );
    }

    public function getMarketOffers(Account $account): string
    {
        return $this->sendServerCall(
            $account,
            1061,
            null,
            'TRADE',
            'GetAvailableOffers',
            'com.bluebyte.game.servlet.TradeWindowHandler'
        );
    }

    public function getFriendList(Account $account): string
    {
        $getFriends = new defaultGame_Communication_VO_dGetFriendsVO;
        $getFriends->version = 'fe5e82453230b4145854f220221b9360f33dec92';

        return $this->sendServerCall(
            $account,
            1014,
            $getFriends,
            'PLAYER',
            'GetFriends',
            'com.bluebyte.game.servlet.PlayerHandler'
        );
    }

    public function stopProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(0, $grid, 0, null);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    public function startProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(1, $grid, 0, null);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    public function applyBuff(Account $account, int $grid, int $uniqueId1, int $uniqueId2, int $amount = 1, ?int $targetZoneId = null): string
    {
        $buffUid = new defaultGame_Communication_VO_dUniqueID;
        $buffUid->uniqueID1 = $uniqueId1;
        $buffUid->uniqueID2 = $uniqueId2;

        $action = $this->buildServerAction(0, $grid, $amount, $buffUid);

        return $this->sendServerCall(
            $account,
            self::CMD_APPLY_BUFF,
            $action,
            'SMC',
            'ExecuteServerCall',
            'com.bluebyte.game.servlet.EventHandler',
            $targetZoneId
        );
    }

    public function sendSpecialist(Account $account, int $taskType, int $subTaskId, int $uniqueId1, int $uniqueId2): string
    {
        $specUid = new defaultGame_Communication_VO_dUniqueID;
        $specUid->uniqueID1 = $uniqueId1;
        $specUid->uniqueID2 = $uniqueId2;

        $taskVo = new defaultGame_Communication_VO_dStartSpecialistTaskVO;
        $taskVo->uniqueID = $specUid;
        $taskVo->subTaskID = $subTaskId;
        $taskVo->paramString = '';

        $action = $this->buildServerAction($taskType, 0, 0, $taskVo);

        return $this->sendServerCall($account, self::CMD_SET_TASK, $action);
    }
}

class defaultGame_Communication_VO_dUniqueID
{
    public $uniqueID1;

    public $uniqueID2;
}

class defaultGame_Communication_VO_dStartSpecialistTaskVO
{
    public $uniqueID;

    public $subTaskID;

    public $paramString;
}

class defaultGame_Communication_VO_dGetFriendsVO
{
    public $version;
}
