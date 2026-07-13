<?php

namespace App\Services;

use App\Models\Account;
use Exception;

/**
 * Minimal AMF3 Encoder for TSO protocol.
 */
class Amf3Encoder
{
    private string $out = '';

    public function encode($data): void
    {
        if (is_null($data)) {
            $this->out .= chr(0x01);
        } elseif (is_bool($data)) {
            $this->out .= $data ? chr(0x03) : chr(0x02);
        } elseif (is_int($data)) {
            if ($data >= -268435456 && $data <= 268435455) {
                $this->out .= chr(0x04);
                $this->writeU29($data);
            } else {
                $this->out .= chr(0x05);
                $this->writeDouble($data);
            }
        } elseif (is_float($data)) {
            $this->out .= chr(0x05);
            $this->writeDouble($data);
        } elseif (is_string($data)) {
            $this->out .= chr(0x06);
            $this->writeString($data);
        } elseif (is_array($data)) {
            if (array_keys($data) !== range(0, count($data) - 1)) {
                // Associative array → dynamic object
                $this->out .= chr(0x0A);
                $this->out .= chr(0x0B);
                $this->writeString('');
                foreach ($data as $k => $v) {
                    $this->writeString((string) $k);
                    $this->encode($v);
                }
                $this->writeString('');
            } else {
                // Strict array
                $this->out .= chr(0x09);
                $this->writeU29((count($data) << 1) | 1);
                $this->writeString('');
                foreach ($data as $v) {
                    $this->encode($v);
                }
            }
        } elseif (is_object($data)) {
            $this->out .= chr(0x0A);
            $className = get_class($data);
            if ($className === 'stdClass') {
                $className = '';
            } else {
                // Strip namespace if present (e.g. App\Services\flex_messaging... -> flex_messaging...)
                if (str_contains($className, '\\')) {
                    $className = substr($className, strrpos($className, '\\') + 1);
                }
            }

            // Map underscore class names to AS3 dot-separated names
            if (str_starts_with($className, 'defaultGame_') || str_starts_with($className, 'Communication_') || str_starts_with($className, 'flex_messaging_')) {
                $className = str_replace('_', '.', $className);
            }

            $props     = get_object_vars($data);
            $propCount = count($props);

            $this->writeU29(($propCount << 4) | 0x03);
            $this->writeString($className);

            foreach ($props as $k => $v) {
                $this->writeString((string) $k);
            }
            foreach ($props as $v) {
                $this->encode($v);
            }
        }
    }

    private function writeU29(int $value): void
    {
        $value = $value & 0x1FFFFFFF;
        if ($value < 0x80) {
            $this->out .= chr($value);
        } elseif ($value < 0x4000) {
            $this->out .= chr(($value >> 7 & 0x7F) | 0x80) . chr($value & 0x7F);
        } elseif ($value < 0x200000) {
            $this->out .= chr(($value >> 14 & 0x7F) | 0x80) . chr(($value >> 7 & 0x7F) | 0x80) . chr($value & 0x7F);
        } else {
            $this->out .= chr(($value >> 22 & 0x7F) | 0x80) . chr(($value >> 15 & 0x7F) | 0x80) . chr(($value >> 8 & 0x7F) | 0x80) . chr($value & 0xFF);
        }
    }

    private function writeDouble(float $value): void
    {
        $this->out .= strrev(pack('d', $value)); // Big endian
    }

    private function writeString(string $value): void
    {
        if ($value === '') {
            $this->out .= chr(0x01);
            return;
        }
        $len = strlen($value);
        $this->writeU29(($len << 1) | 1);
        $this->out .= $value;
    }

    public function getOutput(): string
    {
        return $this->out;
    }
}

// ── VO Classes ──────────────────────────────────────────────────────────────────
// PHP class names use underscores; the encoder maps them to dots for AS3.

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
    public $timestamp  = 0;
    public $timeToLive = 0;
    public $messageId;
    public $clientId   = null;
    public $headers;
    public $body;
}

// ── AMF0 Envelope wrapper ───────────────────────────────────────────────────────

function wrapAmf0Remoting(string $targetUri, string $responseUri, string $amf3Body): string
{
    $out = '';
    $out .= pack('n', 3);          // AMF0 Version 3
    $out .= pack('n', 0);          // Headers count
    $out .= pack('n', 1);          // Bodies count
    $out .= pack('n', strlen($targetUri)) . $targetUri;
    $out .= pack('n', strlen($responseUri)) . $responseUri;
    $out .= pack('N', 0xFFFFFFFF); // Body length (-1)
    $out .= chr(0x11);             // AMF0 marker for AMF3
    $out .= $amf3Body;

    return $out;
}

// ── Load-balancer resolver ──────────────────────────────────────────────────────

function getRealAmfUrl(string $bbUrl, string $dsoAuthUser, string $dsoAuthToken, string $cookieFile): string
{
    $lsUrl        = $bbUrl;
    $amfServerUrl = '';

    $maxRetries = 20;
    for ($i = 0; $i < $maxRetries; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($lsUrl, '/') . '/Z' . (time() * 1000));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);

        $data = http_build_query([
            'zoneID'       => 0,
            'DSOAUTHTOKEN' => $dsoAuthToken,
            'DSOAUTHUSER'  => $dsoAuthUser,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Referer: ' . $lsUrl,
            'Origin: ' . rtrim($lsUrl, '/'),
        ]);

        $lsRes    = curl_exec($ch);
        $lsStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($lsStatus != 202) {
            $amfServerUrl = str_replace(':123443', '', trim($lsRes));
            break;
        }
        sleep(2);
    }

    if (empty($amfServerUrl)) {
        throw new Exception("Timeout waiting for Load Server. Last status: {$lsStatus}, Resp: {$lsRes}");
    }

    if (!str_starts_with($amfServerUrl, 'http')) {
        if (str_starts_with($amfServerUrl, '//')) {
            $amfServerUrl = 'https:' . $amfServerUrl;
        } else {
            $amfServerUrl = rtrim($lsUrl, '/') . $amfServerUrl;
        }
    }

    return $amfServerUrl;
}

// ── AMF Client ──────────────────────────────────────────────────────────────────

class TsoAmfClient
{
    private string $serverUrl;
    private string $cookieFile;
    private string $dsId = 'nil';

    public function __construct(string $serverUrl, string $cookieFile)
    {
        $this->serverUrl  = $serverUrl;
        $this->cookieFile = $cookieFile;
    }

    public function setDsId(string $dsId): void
    {
        $this->dsId = $dsId;
    }

    public function sendCommand($dServerCall, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler'): string
    {
        $message              = new flex_messaging_messages_RemotingMessage();
        $message->destination = $destination;
        $message->operation   = $operation;
        $message->source      = $source;
        $message->messageId   = sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535), mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
        );
        $message->headers            = new \stdClass();
        $message->headers->DSId      = $this->dsId;
        $message->headers->DSEndpoint = 'SMC-Endpoint';
        $message->body               = [$dServerCall];

        $encoder = new Amf3Encoder();
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
            'Connection: Keep-Alive',
            'Accept: */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Referer: ' . $this->serverUrl,
            'Origin: ' . dirname($this->serverUrl),
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $amf0Envelope);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new Exception("AMF cURL Error: " . $error);
        }
        if ($httpCode != 200) {
            throw new Exception("AMF Server returned HTTP {$httpCode}. Response: " . $response);
        }

        return $response;
    }
}

// ── Main Service ────────────────────────────────────────────────────────────────

class TsoAmfService
{
    // Command IDs
    public const CMD_BUILD           = 50;
    public const CMD_UPGRADE         = 60;
    public const CMD_APPLY_BUFF      = 61;
    public const CMD_SET_TASK        = 95;
    public const CMD_STOP_PRODUCTION = 107;
    public const CMD_GET_ZONE        = 1001;

    private TsoAuthService $authService;
    private ?TsoAmfClient $client = null;
    private string $dsId = 'nil';

    public function __construct(TsoAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function setDsId(string $dsId): void
    {
        $this->dsId = $dsId;
        if ($this->client) {
            $this->client->setDsId($dsId);
        }
    }

    /**
     * Build a dServerCall for the given account.
     */
    private function buildServerCall(Account $account, int $type, $actionData): defaultGame_Communication_VO_dServerCall
    {
        $call                       = new defaultGame_Communication_VO_dServerCall();
        $call->dsoAuthToken         = $account->dso_auth_token;
        $call->dsoAuthUser          = (int) $account->dso_auth_user;
        $call->zoneID               = (int) $account->dso_auth_user;
        $call->type                 = $type;
        $call->dsoAuthRandomClientID = 1;
        $call->data                 = $actionData;

        return $call;
    }

    /**
     * Build a dServerAction.
     */
    private function buildServerAction(int $type, int $grid, int $endGrid, $data = null): defaultGame_Communication_VO_dServerAction
    {
        $action          = new defaultGame_Communication_VO_dServerAction();
        $action->type    = $type;
        $action->grid    = $grid;
        $action->endGrid = $endGrid;
        $action->data    = $data;

        return $action;
    }

    /**
     * Get an AMF client connected to the real game server.
     */
    private function getClient(Account $account): TsoAmfClient
    {
        if ($this->client === null) {
            $cookieFile   = $this->authService->getCookieFile($account);
            $amfServerUrl = getRealAmfUrl(
                $account->bb_url,
                $account->dso_auth_user,
                $account->dso_auth_token,
                $cookieFile
            );

            $this->client = new TsoAmfClient($amfServerUrl, $cookieFile);
            $this->client->setDsId($this->dsId);
        }

        return $this->client;
    }

    /**
     * Send a server call via AMF.
     */
    private function sendServerCall(Account $account, int $commandType, $actionData, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler'): string
    {
        try {
            $client = $this->getClient($account);
            $call   = $this->buildServerCall($account, $commandType, $actionData);
            return $client->sendCommand($call, $destination, $operation, $source);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            // If the error seems related to expired session or 301 redirect from load server
            if (str_contains($errorMsg, 'Load Server') || str_contains($errorMsg, '301') || str_contains($errorMsg, 'HTTP 500') || str_contains($errorMsg, 'HTTP 401') || str_contains($errorMsg, 'HTTP 403')) {
                try {
                    // Force a fresh login to retrieve new tokens
                    $this->authService->login($account);
                    $account->refresh();

                    // Re-try the request with fresh tokens
                    $client = $this->getClient($account);
                    $call   = $this->buildServerCall($account, $commandType, $actionData);
                    return $client->sendCommand($call, $destination, $operation, $source);
                } catch (Exception $retryException) {
                    throw new Exception($e->getMessage() . " (Auto-relogin also failed: " . $retryException->getMessage() . ")");
                }
            }
            throw $e;
        }
    }

    // ── Public Commands ─────────────────────────────────────────────────────

    /**
     * GET_ZONE – retrieve the full zone data.
     */
    public function getZone(Account $account): string
    {
        return $this->sendServerCall($account, self::CMD_GET_ZONE, false);
    }

    /**
     * GET_MARKET_OFFERS – retrieve current trade updates/offers from the market.
     */
    public function getMarketOffers(Account $account): string
    {
        return $this->sendServerCall($account, 1061, null);
    }

    /**
     * GET_FRIEND_LIST – retrieve the friends list.
     */
    public function getFriendList(Account $account): string
    {
        $getFriends = new defaultGame_Communication_VO_dGetFriendsVO();
        $getFriends->version = "1843-Release_queen";
        return $this->sendServerCall(
            $account, 
            1014, // COMMAND.GET_FRIEND_LIST
            $getFriends, 
            'PLAYER', 
            'GetFriends', 
            'com.bluebyte.game.servlet.PlayerHandler'
        );
    }

    /**
     * STOP_PRODUCTION – type=0 stops, type=1 starts.
     */
    public function stopProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(self::CMD_STOP_PRODUCTION, $grid, 0, 0);
        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    /**
     * START_PRODUCTION – internally STOP_PRODUCTION with data=1.
     */
    public function startProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(self::CMD_STOP_PRODUCTION, $grid, 0, 1);
        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    /**
     * APPLY_BUFF – apply a buff item to a building.
     *
     * @param int $grid       Building grid
     * @param int $uniqueId1  Buff dUniqueID part 1
     * @param int $uniqueId2  Buff dUniqueID part 2
     */
    public function applyBuff(Account $account, int $grid, int $uniqueId1, int $uniqueId2): string
    {
        $buffUid = new Communication_VO_dUniqueID();
        $buffUid->uniqueID1 = $uniqueId1;
        $buffUid->uniqueID2 = $uniqueId2;

        $action = $this->buildServerAction(0, $grid, 0, $buffUid);

        return $this->sendServerCall($account, self::CMD_APPLY_BUFF, $action);
    }

    /**
     * SET_TASK – send a specialist (geologist/explorer) on a task.
     *
     * @param int $taskType   Task type (specialist category)
     * @param int $subTaskId  Sub-task identifier
     * @param int $uniqueId1  Specialist dUniqueID part 1
     * @param int $uniqueId2  Specialist dUniqueID part 2
     */
    public function sendSpecialist(Account $account, int $taskType, int $subTaskId, int $uniqueId1, int $uniqueId2): string
    {
        $specUid = new Communication_VO_dUniqueID();
        $specUid->uniqueID1 = $uniqueId1;
        $specUid->uniqueID2 = $uniqueId2;

        $taskVo = new Communication_VO_dStartSpecialistTaskVO();
        $taskVo->uniqueID = $specUid;
        $taskVo->subTaskID = $subTaskId;
        $taskVo->paramString = "";

        $action = $this->buildServerAction($taskType, 0, 0, $taskVo);

        return $this->sendServerCall($account, self::CMD_SET_TASK, $action);
    }
}

class Communication_VO_dUniqueID
{
    public $uniqueID1;
    public $uniqueID2;
}

class Communication_VO_dStartSpecialistTaskVO
{
    public $uniqueID;
    public $subTaskID;
    public $paramString;
}

class defaultGame_Communication_VO_dGetFriendsVO
{
    public $version;
}
