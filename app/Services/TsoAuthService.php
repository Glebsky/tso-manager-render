<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Support\Security\CredentialRedactor;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TsoAuthService
{
    /**
     * Server configurations per region.
     *
     * @var array<string, array{domain: string, uplay: string, main: string, play: string}>
     */
    private const SERVERS = [
        'de' => ['domain' => 'https://www.diesiedleronline.de',       'uplay' => '/de/api/user/uplay', 'main' => '/de/startseite',                                             'play' => '/de/spielen'],
        'us' => ['domain' => 'https://www.thesettlersonline.net',     'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
        'en' => ['domain' => 'https://www.thesettlersonline.com',     'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
        'fr' => ['domain' => 'https://www.thesettlersonline.fr',      'uplay' => '/fr/api/user/uplay', 'main' => '/fr/page-de-d%C3%A9marrage',                                 'play' => '/fr/jouer'],
        'ru' => ['domain' => 'https://www.thesettlersonline.ru',      'uplay' => '/ru/api/user/uplay', 'main' => '/ru/%D0%B3%D0%BB%D0%B0%D0%B2%D0%BD%D0%B0%D1%8F-%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0', 'play' => '/ru/play'],
        'pl' => ['domain' => 'https://www.thesettlersonline.pl',      'uplay' => '/pl/api/user/uplay', 'main' => '/pl/strona-g%C5%82%C3%B3wna',                                'play' => '/pl/graj'],
        'es2' => ['domain' => 'https://www.thesettlersonline.es',      'uplay' => '/es/api/user/uplay', 'main' => '/es/p%C3%A1gina-de-inicio',                                  'play' => '/es/jugar'],
        'es' => ['domain' => 'https://www.juego-thesettlersonline.com', 'uplay' => '/es/api/user/uplay', 'main' => '/es/p%C3%A1gina-de-inicio',                                'play' => '/es/jugar'],
        'nl' => ['domain' => 'https://www.thesettlersonline.nl',      'uplay' => '/nl/api/user/uplay', 'main' => '/nl/homepage',                                               'play' => '/nl/play'],
        'cz' => ['domain' => 'https://www.thesettlersonline.cz',      'uplay' => '/cz/api/user/uplay', 'main' => '/cs/domovsk%C3%A1-str%C3%A1nka',                              'play' => '/cs/play'],
        'pt' => ['domain' => 'https://www.thesettlersonline.com.br',  'uplay' => '/pt/api/user/uplay', 'main' => '/pt/p%C3%A1gina-inicial',                                    'play' => '/pt/jogar'],
        'it' => ['domain' => 'https://www.thesettlersonline.it',      'uplay' => '/it/api/user/uplay', 'main' => '/it/homepage',                                               'play' => '/it/gioca'],
        'el' => ['domain' => 'https://www.thesettlersonline.gr',      'uplay' => '/el/api/user/uplay', 'main' => '/el/%CE%B1%CF%81%CF%87%CE%B9%CE%BA%CE%AE-%CF%83%CE%B5%CE%BB%CE%AF%CE%B4%CE%B1', 'play' => '/el/play'],
        'ro' => ['domain' => 'https://www.thesettlersonline.ro',      'uplay' => '/ro/api/user/uplay', 'main' => '/ro/pagina-de-start',                                        'play' => '/ro/play'],
        'ts' => ['domain' => 'https://www.tsotesting.com',            'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
    ];

    /**
     * Get the cookie file path for a given account.
     */
    public function getCookieFile(Account $account): string
    {
        $dir = storage_path('app/cookies');
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }

        return $dir.'/account_'.$account->id.'.txt';
    }

    /**
     * Clear active session state (cookie files) for an account.
     */
    public function resetSession(Account $account): void
    {
        $cookieFile = $this->getCookieFile($account);
        if (file_exists($cookieFile)) {
            @unlink($cookieFile);
        }
    }

    /**
     * Authenticate the account and store tokens.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function login(Account $account): array
    {
        $lock = Cache::lock("tso:login_lock:{$account->id}", 60);
        $locked = false;

        try {
            $locked = (bool) $lock->block(25);
        } catch (\Throwable $e) {
            Log::warning("[TsoAuth] Proceeding without login lock for account #{$account->id}: ".$e->getMessage());
        }

        try {
            return $this->performLogin($account);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    /**
     * Internal login routine executing authentication against TSO server.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function performLogin(Account $account): array
    {
        $cooldownKey = "account_login_cooldown:{$account->id}";
        if (Cache::has($cooldownKey)) {
            $reason = Cache::get($cooldownKey);
            $msg = is_string($reason) && ! empty($reason) ? $reason : __('ui.auth.captcha_required');

            throw new Exception($msg);
        }

        $region = (string) $account->region;
        if (! isset(self::SERVERS[$region])) {
            throw new Exception("Invalid region: {$region}");
        }

        $server = self::SERVERS[$region];
        $cookieFile = $this->getCookieFile($account);
        $this->resetSession($account);

        try {
            $params = $this->loginLegacy($account, $cookieFile, $server);
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
            Log::warning("[TsoAuth] Legacy (CipMigrated) login failed for account #{$account->id}: ".CredentialRedactor::redact($errMsg, $account));

            if ($this->isCaptchaOr2faError($errMsg)) {
                Cache::put($cooldownKey, $errMsg, 900);
                $account->update(['status' => 'session_expired']);

                throw $e;
            }

            try {
                $params = $this->loginOAuth($account, $cookieFile, $server);
            } catch (Exception $e2) {
                $oauthErrMsg = $e2->getMessage();
                Log::warning("[TsoAuth] OAuth fallback login also failed for account #{$account->id}: ".CredentialRedactor::redact($oauthErrMsg, $account));

                if ($this->isCaptchaOr2faError($oauthErrMsg)) {
                    Cache::put($cooldownKey, $oauthErrMsg, 900);
                    $account->update(['status' => 'session_expired']);

                    throw $e2;
                }

                Cache::put($cooldownKey, $errMsg, 300);

                throw $e;
            }
        }

        Cache::forget($cooldownKey);

        $account->update([
            'dso_auth_user' => $params['dsoAuthUser'],
            'dso_auth_token' => $params['dsoAuthToken'],
            'bb_url' => $params['bburl'],
            'nickname' => $params['nickName'],
            'status' => 'online',
        ]);

        return $params;
    }

    public function isCaptchaOr2faError(string $message): bool
    {
        return str_contains($message, 'CAPTCHA') ||
            str_contains($message, 'captcha') ||
            str_contains($message, 'Captcha') ||
            str_contains($message, '2FA') ||
            str_contains($message, 'twoFactor') ||
            str_contains($message, __('ui.auth.captcha_required')) ||
            str_contains($message, __('ui.auth.2fa_required'));
    }

    /**
     * Legacy form-based login (CipMigratedAuth).
     *
     * @param  array{domain: string, uplay: string, main: string, play: string}  $server
     * @return array<string, mixed>
     */
    public function loginLegacy(Account $account, string $cookieFile, array $server): array
    {
        $loginUrl = $server['domain'].str_replace('uplay', 'login', $server['uplay']);
        $loginRes = $this->cipMigratedRequest($loginUrl, [
            'name' => (string) $account->username,
            'password' => (string) $account->password,
        ], $cookieFile);

        Log::info('[TsoAuth] Legacy (CipMigrated) login response: '.CredentialRedactor::redact(substr($loginRes, 0, 500), $account));

        if (strpos($loginRes, 'OKAY') === false) {
            if (str_contains($loginRes, 'CAPTCHA') || str_contains($loginRes, 'captcha') || str_contains($loginRes, 'Captcha')) {
                throw new Exception(__('ui.auth.captcha_required'));
            }
            throw new Exception('Login failed: '.CredentialRedactor::redact($loginRes, $account));
        }

        $mainUrl = $server['domain'].$server['main'];
        $this->cipMigratedRequest($mainUrl, ['start' => '1'], $cookieFile);

        $playUrl = $server['domain'].$server['play'];
        $playHtml = $this->cipMigratedRequest($playUrl, null, $cookieFile);

        return $this->extractParams($playHtml);
    }

    /**
     * HTTP request that mimics C# PostSubmitter with useBC=true.
     *
     * @param  array<string, string>|null  $postData
     */
    private function cipMigratedRequest(string $url, ?array $postData, string $cookieFile): string
    {
        $maxRedirects = 10;
        $currentUrl = $url;
        $isPost = ($postData !== null);
        $postFields = $isPost ? http_build_query($postData) : null;

        for ($i = 0; $i < $maxRedirects; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));

            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'Connection: close',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($isPost && $i === 0) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }

            $response = (string) curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if ($error) {
                throw new Exception('cURL Error: '.$error);
            }

            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            if ($httpCode >= 300 && $httpCode < 400) {
                if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headerText, $matches)) {
                    $location = trim($matches[1]);
                    if (! str_starts_with($location, 'http')) {
                        $parsed = parse_url($currentUrl);
                        $location = $parsed['scheme'].'://'.$parsed['host'].$location;
                    }
                    $currentUrl = $location;

                    continue;
                }
            }

            return $body;
        }

        throw new Exception('Too many redirects for URL: '.$url);
    }

    /**
     * Modern Ubisoft Connect OAuth login flow.
     *
     * @param  array{domain: string, uplay: string, main: string, play: string}  $server
     * @return array<string, mixed>
     */
    public function loginOAuth(Account $account, string $cookieFile, array $server): array
    {
        $oauthStartUrl = $server['domain'].'/oauth/start';
        $redirectUrl = $this->curlRequest($oauthStartUrl, null, $cookieFile, true);

        $redirectUrl2 = $this->curlRequest($redirectUrl, null, $cookieFile, true);

        $urlParts = parse_url($redirectUrl2);
        if (! isset($urlParts['query'])) {
            throw new Exception('OAuth redirect URL query parameters missing: '.$redirectUrl2);
        }
        parse_str($urlParts['query'], $redirectUrlOpts);
        $clientId = $redirectUrlOpts['client_id'] ?? null;
        if (! $clientId) {
            throw new Exception('Could not find client_id in Ubisoft redirect URL');
        }

        $oauthTokenUrl = 'https://connect.ubisoft.com/v2/webauth/public/ubiservices/oauthToken';
        $oauthTokenBody = json_encode([
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
        $oauthTokenHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $oauthTokenRes = $this->curlRequest($oauthTokenUrl, $oauthTokenBody, $cookieFile, false, $oauthTokenHeaders);
        $oauthTokenData = json_decode($oauthTokenRes, true);
        $accessToken = $oauthTokenData['accessToken'] ?? null;
        if (! $accessToken) {
            throw new Exception('Ubisoft login failed (could not get oauthToken): '.$oauthTokenRes);
        }

        $authTokenUrl = 'https://api.partners.ubisoft.com/v1/profiles/authentication/token';
        $authTokenBody = json_encode(['rememberMe' => true]);
        $credentials = base64_encode(trim((string) $account->username).':'.trim((string) $account->password));
        $authTokenHeaders = [
            'Content-Type: application/json',
            'Ubi-RequestedPlatformType: uplay',
            'Authorization: Bearer '.$accessToken,
            'Ubi-Profile-Authorization: Basic '.$credentials,
        ];
        $authTokenRes = $this->curlRequest($authTokenUrl, $authTokenBody, $cookieFile, false, $authTokenHeaders);
        $authTokenData = json_decode($authTokenRes, true);

        if (isset($authTokenData['twoFactorAuthenticationTicket'])) {
            throw new Exception(__('ui.auth.2fa_required'));
        }

        $token = $authTokenData['token'] ?? null;
        if (! $token) {
            throw new Exception('Ubisoft authentication failed (could not get token): '.$authTokenRes);
        }

        $redirectUrlOpts['token'] = $token;
        $authorizeCallbackUrl = 'https://api.partners.ubisoft.com/v1/oauth/authorize/callback?'.http_build_query($redirectUrlOpts);
        $callbackRedirect = $this->curlRequest($authorizeCallbackUrl, null, $cookieFile, true);

        $callbackParts = parse_url($callbackRedirect);
        if (! isset($callbackParts['query'])) {
            throw new Exception('Authorize callback query missing: '.$callbackRedirect);
        }
        parse_str($callbackParts['query'], $callbackOpts);
        $consentRedirectUrl = $callbackOpts['redirectUrl'] ?? null;
        if (! $consentRedirectUrl) {
            throw new Exception('Consent redirectUrl missing in callback redirect: '.$callbackRedirect);
        }
        $consentRedirectParts = parse_url((string) $consentRedirectUrl);
        parse_str($consentRedirectParts['query'] ?? '', $consentOpts);
        $profileToken = $consentOpts['profile_token'] ?? null;

        unset($redirectUrlOpts['token']);
        $redirectUrlOpts['profile_token'] = $profileToken;
        $consentUrl = 'https://api.partners.ubisoft.com/v1/oauth/consents';
        $consentBody = json_encode([
            'scopesConsented' => ['offline_access', 'openid', 'profile', 'email'],
            'isConsented' => true,
            'redirectUrl' => 'https://api.partners.ubisoft.com/v1/oauth/authorize/callback?'.http_build_query($redirectUrlOpts),
        ]);
        $consentHeaders = [
            'Content-Type: application/json',
            'Ubi-RequestedPlatformType: uplay',
            'ClientId: '.$clientId,
        ];
        $consentRes = $this->curlRequest($consentUrl, $consentBody, $cookieFile, true, $consentHeaders);

        $finalCallbackUrl = $this->curlRequest($consentRes, null, $cookieFile, true);

        $login2Url = str_replace('/login?', '/login2?', $finalCallbackUrl);
        if ($login2Url === $finalCallbackUrl) {
            $login2Url = str_replace('/login', '/login2', $finalCallbackUrl);
        }
        $afterLogin2Url = $this->curlRequest($login2Url, null, $cookieFile, true);

        $this->curlRequest($afterLogin2Url, null, $cookieFile);

        $mainUrl = $server['domain'].$server['main'];
        $this->curlRequest($mainUrl, null, $cookieFile);

        $playUrl = $server['domain'].$server['play'];
        $playHtml = $this->curlRequest($playUrl, null, $cookieFile);

        return $this->extractParams($playHtml);
    }

    /**
     * Check if the account already has valid tokens.
     */
    public function isAuthenticated(Account $account): bool
    {
        return ! empty($account->dso_auth_token) && ! empty($account->dso_auth_user) && ! empty($account->bb_url);
    }

    /**
     * Get server config for a region.
     *
     * @return array{domain: string, uplay: string, main: string, play: string}|null
     */
    public static function getServerConfig(string $region): ?array
    {
        return self::SERVERS[$region] ?? null;
    }

    /**
     * Get list of all supported regions.
     *
     * @return list<string>
     */
    public static function supportedRegions(): array
    {
        return array_keys(self::SERVERS);
    }

    /**
     * Extract flash vars from the play page HTML.
     *
     * @return array<string, mixed>
     */
    private function extractParams(string $html): array
    {
        $params = [];
        if (preg_match('/return\s+"([^"]+)"/i', $html, $matches) || preg_match('/thisProgram:\s+"([^"]+)"/i', $html, $matches)) {
            parse_str($matches[1], $parsedParams);
            $params = $parsedParams;
        }

        $nickName = 'Unknown';
        if (preg_match("/loggedInUserName\s*=\s*'([^']+)'/i", $html, $matches)) {
            $nickName = $matches[1];
        }

        Storage::disk('local')->put('debug/play_page.html', $html);

        if (empty($params) || ! isset($params['dsoAuthToken'])) {
            throw new Exception('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

        Storage::disk('local')->put('debug/flash_vars.json', (string) json_encode($params, JSON_PRETTY_PRINT));

        return [
            'dsoAuthToken' => $params['dsoAuthToken'],
            'dsoAuthUser' => $params['dsoAuthUser'],
            'bburl' => $params['bb'],
            'zoneId' => $params['zoneID'] ?? null,
            'nickName' => $nickName,
        ];
    }

    /**
     * Perform a cURL request with cookie support.
     *
     * @param  array<string, mixed>|string|null  $postData
     * @param  list<string>|null  $headers
     */
    private function curlRequest(string $url, mixed $postData, string $cookieFile, bool $returnRedirect = false, ?array $headers = null): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        if ($returnRedirect) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
            'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
        ];

        if ($headers) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }

        $response = (string) curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: '.$error);
        }

        if ($returnRedirect) {
            if (preg_match('/^Location:\s*([^\r\n]+)/mi', $response, $matches)) {
                return trim($matches[1]);
            }
            if (isset($info['redirect_url']) && $info['redirect_url']) {
                return (string) $info['redirect_url'];
            }

            return $response;
        }

        return $response;
    }
}
