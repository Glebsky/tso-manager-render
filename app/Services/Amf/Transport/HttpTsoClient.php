<?php

declare(strict_types=1);

namespace App\Services\Amf\Transport;

use App\Models\Account;
use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Vo\flex_messaging_messages_RemotingMessage;
use App\Services\TsoAuthService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Throwable;

class HttpTsoClient implements TsoClientInterface
{
    /** @var array<string, array{url: string, cookie_file: string, ds_id: string, auth_token: string, resolved_at: float}> */
    private array $clients = [];

    /**
     * Per-account fallback DSIds extracted during authentication.
     * Stored strictly per account ID to eliminate cross-account pollution.
     *
     * @var array<int, string>
     */
    private array $dsIds = [];

    public function __construct(
        private readonly TsoAuthService $authService,
    ) {}

    public function setDsId(string $dsId, ?int $accountId = null): void
    {
        if ($accountId !== null) {
            $this->dsIds[$accountId] = $dsId;
        } else {
            $this->dsIds[0] = $dsId;
        }
    }

    /**
     * Drop cached in-memory transport state for the current process.
     * Pass an account id to drop only that account's entries; omit it to drop everything.
     * Does NOT invalidate the shared cache across processes.
     */
    public function resetClients(?int $accountId = null): void
    {
        if ($accountId === null) {
            $this->clients = [];
            $this->dsIds = [];

            return;
        }

        $prefix = $accountId.':';

        foreach (array_keys($this->clients) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->clients[$key]);
            }
        }

        unset($this->dsIds[$accountId]);
    }

    /**
     * Invalidate the shared game session for an account across every process.
     * Bumps the generation counter so all workers and web processes will resolve
     * a fresh session on their next call.
     */
    public function invalidateSession(int $accountId): void
    {
        $this->resetClients($accountId);

        Cache::put(
            "tso:amf_gen:{$accountId}",
            $this->sessionGeneration($accountId) + 1,
            now()->addDay(),
        );

        Log::info("[TsoAmf] Invalidated shared game session cache for account #{$accountId} (gen {$this->sessionGeneration($accountId)})");
    }

    private function sessionGeneration(int $accountId): int
    {
        return (int) Cache::remember("tso:amf_gen:{$accountId}", now()->addDay(), static fn (): int => 1);
    }

    private function sharedSessionKey(int $accountId, int $zoneId): string
    {
        return sprintf('tso:amf_session:%d:%d:%d', $accountId, $this->sessionGeneration($accountId), $zoneId);
    }

    /**
     * Read the game session shared by every process (scheduler, queue worker,
     * web requests). Records are bound to the web auth token they were created
     * with, so a re-login automatically invalidates them.
     *
     * @return array{url: string, cookie_file: string, ds_id: string, auth_token: string, resolved_at: float}|null
     */
    private function readSharedSession(int $accountId, int $zoneId, string $authToken): ?array
    {
        $record = Cache::get($this->sharedSessionKey($accountId, $zoneId));

        if (! is_array($record)
            || ! isset($record['url'], $record['cookie_file'], $record['ds_id'], $record['auth_token'], $record['resolved_at'])
            || ! is_string($record['url'])
            || ! is_string($record['cookie_file'])
            || ! is_string($record['ds_id'])
            || ! is_string($record['auth_token'])
            || ! is_numeric($record['resolved_at'])
            || $record['auth_token'] !== $authToken
        ) {
            return null;
        }

        return [
            'url' => $record['url'],
            'cookie_file' => $record['cookie_file'],
            'ds_id' => $record['ds_id'],
            'auth_token' => $record['auth_token'],
            'resolved_at' => (float) $record['resolved_at'],
        ];
    }

    /**
     * @param  array{url: string, cookie_file: string, ds_id: string, auth_token: string, resolved_at: float}  $session
     */
    private function writeSharedSession(int $accountId, int $zoneId, array $session): void
    {
        Cache::put(
            $this->sharedSessionKey($accountId, $zoneId),
            $session,
            now()->addSeconds((int) config('game.session_ttl_seconds', 300)),
        );
    }

    /**
     * The game server assigns the real DSId and echoes it back in the AMF
     * acknowledge headers. Reusing the value derived from the load-server hash
     * keeps pointing at a session the server has already superseded, which it
     * reports as error 1012.
     */
    private function extractDsIdFromResponse(string $response): ?string
    {
        if ($response === '') {
            return null;
        }

        $pattern = '/DSId.{0,12}?([0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12})/s';

        return preg_match($pattern, $response, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @throws Exception
     */
    public function resolveServerUrl(Account $account, int $targetZoneId = 0, ?string &$dsId = null): string
    {
        $lsUrl = (string) $account->bb_url;
        $scheme = parse_url($lsUrl, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException("Invalid or unsupported URL scheme for account bb_url: {$scheme}");
        }

        $dsoAuthUser = (string) $account->dso_auth_user;
        $dsoAuthToken = (string) $account->dso_auth_token;
        $cookieFile = $this->authService->getCookieFile($account);
        if ($cookieFile === '') {
            throw new \RuntimeException("Cookie file for account #{$account->id} cannot be empty.");
        }

        Log::info("[TsoAmf] Resolving real AMF server: bbUrl={$lsUrl}, user={$dsoAuthUser}, targetZoneId={$targetZoneId}");

        $authUrl = rtrim($lsUrl, '/').'/authenticate';
        $chAuth = curl_init();
        curl_setopt($chAuth, CURLOPT_URL, $authUrl);
        curl_setopt($chAuth, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chAuth, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($chAuth, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
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

        $maxRetries = 20;
        $lsStatus = 0;
        $lsRes = '';
        $amfServerUrl = '';

        for ($i = 0; $i < $maxRetries; $i++) {
            $ch = curl_init();
            $requestUrl = rtrim($lsUrl, '/').'/Z'.(int) round(microtime(true) * 1000);
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
            curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
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

        if ($amfServerUrl === '') {
            if ($lsStatus >= 300 && $lsStatus < 400) {
                throw new \RuntimeException("Load Server returned status {$lsStatus}: Session expired or invalid.");
            }
            throw new \RuntimeException("Timeout waiting for Load Server. Last status: {$lsStatus}, Resp: {$lsRes}");
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

    /**
     * @throws RandomException
     */
    public function sendCommand(Account $account, mixed $dServerCall, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
    {
        $accountId = $account->id;
        $lock = Cache::lock("tso:amf_lock:{$accountId}", 60);
        $locked = false;

        try {
            $locked = (bool) $lock->block((int) config('game.session_lock_wait_seconds', 20));
        } catch (Throwable $e) {
            Log::warning("[TsoAmf] Proceeding without session lock for account #{$accountId}: ".$e->getMessage());
        }

        try {
            return $this->dispatchCommand($account, $targetZoneId ?? 0, $dServerCall, $destination, $operation, $source);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    /**
     * @throws RandomException
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    private function dispatchCommand(Account $account, int $zoneId, mixed $dServerCall, string $destination, string $operation, ?string $source): string
    {
        $accountId = $account->id;
        $clientKey = $accountId.':'.$zoneId;
        $authToken = (string) $account->dso_auth_token;
        $ttl = (float) config('game.session_ttl_seconds', 300);

        $session = $this->clients[$clientKey] ?? null;

        if ($session !== null && ($session['auth_token'] !== $authToken || (microtime(true) - $session['resolved_at']) > $ttl)) {
            $session = null;
        }

        if ($session === null) {
            $session = $this->readSharedSession($accountId, $zoneId, $authToken);

            if ($session !== null) {
                Log::info("[TsoAmf] Reusing shared game session for account #{$accountId} (zone {$zoneId}) with DSId {$session['ds_id']}");
            }
        }

        if ($session === null) {
            $dsId = 'nil';
            $amfServerUrl = $this->resolveServerUrl($account, $zoneId, $dsId);

            $resolvedDsId = ($dsId !== null && $dsId !== 'nil') ? $dsId : ($this->dsIds[$accountId] ?? 'nil');
            $cookieFile = $this->authService->getCookieFile($account);
            if ($cookieFile === '' || $amfServerUrl === '') {
                throw new \RuntimeException('Invalid AMF server URL or cookie file.');
            }

            $session = [
                'url' => $amfServerUrl,
                'cookie_file' => $cookieFile,
                'ds_id' => $resolvedDsId,
                'auth_token' => $authToken,
                'resolved_at' => microtime(true),
            ];

            if ($dsId !== null && $dsId !== 'nil') {
                $this->dsIds[$accountId] = $dsId;
            }

            $this->writeSharedSession($accountId, $zoneId, $session);
        }

        $this->clients[$clientKey] = $session;
        $client = $session;
        $clientDsId = $client['ds_id'] !== '' ? $client['ds_id'] : 'nil';

        Log::info("[TsoAmf] Sending command [{$operation}] for account #{$accountId} (zone {$zoneId}) with DSId {$clientDsId}");

        $message = new flex_messaging_messages_RemotingMessage;
        $message->destination = $destination;
        $message->operation = $operation;
        $message->source = $source;
        $message->messageId = sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            random_int(0, 65535), random_int(0, 65535),
            random_int(0, 65535), random_int(16384, 20479),
            random_int(32768, 49151),
            random_int(0, 65535), random_int(0, 65535), random_int(0, 65535)
        );
        $message->headers = (object) [
            'DSId' => $clientDsId,
            'DSEndpoint' => 'SMC-Endpoint',
        ];
        $message->body = [$dServerCall];

        $encoder = new Amf3Encoder;
        $encoder->encode([$message]);
        $amf3Body = $encoder->getOutput();

        $amf0Envelope = $this->wrapAmf0Remoting($amf3Body);

        $startTime = microtime(true);

        if ($client['url'] === '' || $client['cookie_file'] === '') {
            throw new \RuntimeException('Invalid client URL or cookie file.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $client['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $client['cookie_file']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $client['cookie_file']);
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
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error("[TsoAmf] Command [{$operation}] for account #{$accountId} failed after {$durationMs}ms with cURL error: {$error}");
            throw new \RuntimeException('AMF cURL Error: '.$error);
        }
        if ($httpCode !== 200) {
            Log::error("[TsoAmf] Command [{$operation}] for account #{$accountId} returned HTTP {$httpCode} after {$durationMs}ms. Response: ".substr($response, 0, 300));
            throw new \RuntimeException("AMF Server returned HTTP {$httpCode}. Response: ".$response);
        }

        Log::info("[TsoAmf] Command [{$operation}] for account #{$accountId} completed in {$durationMs}ms (HTTP {$httpCode}, ".strlen($response).' bytes)');

        $serverDsId = $this->extractDsIdFromResponse($response);

        if ($serverDsId !== null && $serverDsId !== $client['ds_id']) {
            Log::info("[TsoAmf] Game server assigned DSId {$serverDsId} for account #{$accountId} (was {$clientDsId})");

            $session['ds_id'] = $serverDsId;
            $session['resolved_at'] = microtime(true);
            $this->clients[$clientKey] = $session;
            $this->dsIds[$accountId] = $serverDsId;
            $this->writeSharedSession($accountId, $zoneId, $session);
        }

        return $response;
    }

    private function wrapAmf0Remoting(string $amf3Body, string $targetUri = 'null', string $responseUri = '/1'): string
    {
        $out = pack('n', 3);
        $out .= pack('n', 0);
        $out .= pack('n', 1);
        $out .= pack('n', strlen($targetUri)).$targetUri;
        $out .= pack('n', strlen($responseUri)).$responseUri;
        $out .= pack('N', 0xFFFFFFFF);
        $out .= chr(0x11);
        $out .= $amf3Body;

        return $out;
    }
}
