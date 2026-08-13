<?php

declare(strict_types=1);

namespace App\Services\Amf\Transport;

use App\Models\Account;
use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Vo\flex_messaging_messages_RemotingMessage;
use App\Services\TsoAuthService;
use Exception;
use Illuminate\Support\Facades\Log;

class HttpTsoClient implements TsoClientInterface
{
    /** @var array<string, array{url: string, cookie_file: string}> */
    private array $clients = [];

    private string $dsId = 'nil';

    public function __construct(
        private readonly TsoAuthService $authService,
    ) {}

    public function setDsId(string $dsId): void
    {
        $this->dsId = $dsId;
    }

    public function resetClients(): void
    {
        $this->clients = [];
    }

    public function resolveServerUrl(Account $account, int $targetZoneId = 0, ?string &$dsId = null): string
    {
        $lsUrl = (string) $account->bb_url;
        $dsoAuthUser = (string) $account->dso_auth_user;
        $dsoAuthToken = (string) $account->dso_auth_token;
        $cookieFile = $this->authService->getCookieFile($account);

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

        if ($authStatus === 200 && $authRes !== '') {
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
        $amfServerUrl = '';

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

        if ($amfServerUrl === '') {
            if ($lsStatus === 301 || ($lsStatus >= 300 && $lsStatus < 400)) {
                throw new Exception("Load Server returned status {$lsStatus}: Session expired or invalid.");
            }
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

    public function sendCommand(Account $account, mixed $dServerCall, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
    {
        $zoneId = $targetZoneId ?? 0;
        $clientKey = $account->id.':'.$zoneId;

        if (! isset($this->clients[$clientKey])) {
            $cookieFile = $this->authService->getCookieFile($account);
            $dsId = 'nil';
            $amfServerUrl = $this->resolveServerUrl($account, $zoneId, $dsId);

            $this->clients[$clientKey] = [
                'url' => $amfServerUrl,
                'cookie_file' => $cookieFile,
            ];
            if ($dsId !== 'nil') {
                $this->dsId = $dsId;
            }
        }

        $client = $this->clients[$clientKey];

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
        $message->headers = (object) [
            'DSId' => $this->dsId,
            'DSEndpoint' => 'SMC-Endpoint',
        ];
        $message->body = [$dServerCall];

        $encoder = new Amf3Encoder;
        $encoder->encode([$message]);
        $amf3Body = $encoder->getOutput();

        $amf0Envelope = $this->wrapAmf0Remoting('null', '/1', $amf3Body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $client['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

    private function wrapAmf0Remoting(string $targetUri, string $responseUri, string $amf3Body): string
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
}
