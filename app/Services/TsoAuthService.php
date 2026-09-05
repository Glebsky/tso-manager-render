<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Support\Security\CredentialRedactor;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TsoAuthService
{
    /**
     * Server configurations per region.
     *
     * @var array<string, array{domain: string, uplay: string, main: string, play: string}>
     */
    private const array SERVERS = [
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
     * Browser fingerprint expected by the game backend. Must stay byte-identical
     * to what the reference Flash client sends.
     */
    private const array BROWSER_HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
        'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
    ];

    /** Cache key prefix for the "session was verified recently" flag. */
    private const string SESSION_OK_KEY = 'tso:session_ok:';

    /** How long a positive verifySession() result is trusted, in seconds. */
    private const int SESSION_OK_TTL = 300;

    /** Cache key prefix remembering which login flow last succeeded. */
    private const string AUTH_FLOW_KEY = 'tso:auth_flow:';

    /**
     * Get the cookie file path for a given account.
     */
    public function getCookieFile(Account $account): string
    {
        $dir = storage_path('app/cookies');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir.'/account_'.$account->id.'.txt';
    }

    /**
     * Clear active session state (cookie files) for an account.
     */
    public function resetSession(Account $account): void
    {
        $cookieFile = $this->getCookieFile($account);
        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        $this->forgetSessionVerified($account);
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
        } catch (Throwable $e) {
            Log::warning("[TsoAuth] Proceeding without login lock for account #{$account->id}: ".$e->getMessage());
        }

        try {
            if ($locked) {
                // Another process may have completed a full login while we were
                // waiting for the lock. Logging in again would create a second
                // game session and invalidate the fresh one (error 1012).
                $account->refresh();

                if (Cache::get(self::SESSION_OK_KEY.$account->id) === true) {
                    Log::info("[TsoAuth] Reusing session established by another process for account #{$account->id}");

                    return $this->sessionParams($account);
                }
            }

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

            throw new RuntimeException($msg);
        }

        $region = $account->region;
        if (! isset(self::SERVERS[$region])) {
            throw new RuntimeException("Invalid region: {$region}");
        }

        $server = self::SERVERS[$region];
        $cookieFile = $this->getCookieFile($account);
        $this->resetSession($account);

        $order = $this->preferredFlow($account) === 'oauth'
            ? ['oauth', 'legacy']
            : ['legacy', 'oauth'];

        $params = null;
        $firstException = null;

        foreach ($order as $flow) {
            try {
                $params = $flow === 'oauth'
                    ? $this->loginOAuth($account, $cookieFile, $server)
                    : $this->loginLegacy($account, $cookieFile, $server);

                $this->rememberFlow($account, $flow);

                break;
            } catch (Exception $e) {
                $errMsg = $e->getMessage();
                Log::warning("[TsoAuth] {$flow} login failed for account #{$account->id}: ".CredentialRedactor::redact($errMsg, $account));

                if ($this->isCaptchaOr2faError($errMsg)) {
                    Cache::put($cooldownKey, $errMsg, 900);
                    $account->update(['status' => 'session_expired']);

                    throw $e;
                }

                $firstException ??= $e;
            }
        }

        if ($params === null) {
            throw $firstException ?? new RuntimeException("Login failed for account #{$account->id}");
        }

        Cache::forget($cooldownKey);

        $account->update([
            'dso_auth_user' => $params['dsoAuthUser'],
            'dso_auth_token' => $params['dsoAuthToken'],
            'bb_url' => $params['bburl'],
            'nickname' => $params['nickName'],
            'status' => 'online',
        ]);

        $this->markSessionVerified($account);

        return $params;
    }

    public function clearCooldown(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;
        Cache::forget("account_login_cooldown:{$id}");
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
     *
     * @throws Exception
     */
    public function loginLegacy(Account $account, string $cookieFile, array $server): array
    {
        $loginUrl = $server['domain'].str_replace('uplay', 'login', $server['uplay']);
        $loginRes = $this->cipMigratedRequest($loginUrl, [
            'name' => $account->username,
            'password' => $account->password,
        ], $cookieFile);

        Log::info('[TsoAuth] Legacy (CipMigrated) login response: '.CredentialRedactor::redact(substr($this->formatAuthResponse($loginRes), 0, 500), $account));

        if (! str_contains($loginRes, 'OKAY')) {
            if (stripos($loginRes, 'captcha') !== false) {
                throw new RuntimeException((string) __('ui.auth.captcha_required'));
            }
            $formattedError = $this->formatAuthResponse($loginRes);
            throw new RuntimeException('Login failed: '.CredentialRedactor::redact($formattedError, $account));
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
     *
     * @throws Exception
     */
    private function cipMigratedRequest(string $url, ?array $postData, string $cookieFile): string
    {
        $maxRedirects = 10;
        $currentUrl = $url;
        $isPost = ($postData !== null);
        $postFields = $isPost ? http_build_query($postData) : '';

        for ($i = 0; $i < $maxRedirects; $i++) {
            if ($currentUrl === '' || $cookieFile === '') {
                throw new RuntimeException('Invalid URL or cookie file.');
            }

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
                throw new RuntimeException('cURL Error: '.$error);
            }

            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            if ($httpCode >= 300 && $httpCode < 400 && preg_match('/^Location:\s*([^\r\n]+)/mi', $headerText, $matches)) {
                $location = trim($matches[1]);
                if (! str_starts_with($location, 'http')) {
                    $parsed = parse_url($currentUrl);
                    if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                        $location = $parsed['scheme'].'://'.$parsed['host'].$location;
                    }
                }
                $currentUrl = $location;

                continue;
            }

            return $body;
        }

        throw new RuntimeException('Too many redirects for URL: '.$url);
    }

    /**
     * Modern Ubisoft Connect OAuth login flow.
     *
     * @param  array{domain: string, uplay: string, main: string, play: string}  $server
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function loginOAuth(Account $account, string $cookieFile, array $server): array
    {
        $oauthStartUrl = $server['domain'].'/oauth/start';
        $redirectUrl = $this->curlRequest($oauthStartUrl, null, $cookieFile, true);

        $redirectUrl2 = $this->curlRequest($redirectUrl, null, $cookieFile, true);

        $queryString = parse_url($redirectUrl2, PHP_URL_QUERY);
        if (! is_string($queryString) || $queryString === '') {
            throw new RuntimeException('OAuth redirect URL query parameters missing: '.$redirectUrl2);
        }
        parse_str($queryString, $redirectUrlOpts);
        $clientId = $redirectUrlOpts['client_id'] ?? null;
        if (! $clientId) {
            throw new RuntimeException('Could not find client_id in Ubisoft redirect URL');
        }

        $oauthTokenUrl = 'https://connect.ubisoft.com/v2/webauth/public/ubiservices/oauthToken';
        $oauthTokenBody = (string) json_encode([
            'operationName' => 'SignIn',
            'variables' => [
                'input' => [
                    'rememberMe' => true,
                ],
            ],
            'query' => 'mutation SignIn($input: SignInInput!) { signIn(input: $input) { ... on SignInResultSuccess { accessToken } } }',
        ], JSON_THROW_ON_ERROR);
        $oauthTokenHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $oauthTokenRes = $this->curlRequest($oauthTokenUrl, $oauthTokenBody, $cookieFile, false, $oauthTokenHeaders);
        $oauthTokenData = json_decode($oauthTokenRes, true, 512, JSON_THROW_ON_ERROR);
        $accessToken = is_array($oauthTokenData) ? ($oauthTokenData['accessToken'] ?? null) : null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Ubisoft login failed (could not get oauthToken): '.$this->formatAuthResponse($oauthTokenRes));
        }

        $authTokenUrl = 'https://api.partners.ubisoft.com/v1/profiles/authentication/token';
        $authTokenBody = (string) json_encode(['rememberMe' => true], JSON_THROW_ON_ERROR);
        $credentials = base64_encode(trim($account->username).':'.trim($account->password));
        $authTokenHeaders = [
            'Content-Type: application/json',
            'Ubi-RequestedPlatformType: uplay',
            'Authorization: Bearer '.$accessToken,
            'Ubi-Profile-Authorization: Basic '.$credentials,
        ];
        $authTokenRes = $this->curlRequest($authTokenUrl, $authTokenBody, $cookieFile, false, $authTokenHeaders);
        $authTokenData = json_decode($authTokenRes, true, 512, JSON_THROW_ON_ERROR);

        if (is_array($authTokenData) && isset($authTokenData['twoFactorAuthenticationTicket'])) {
            throw new RuntimeException((string) __('ui.auth.2fa_required'));
        }

        $token = is_array($authTokenData) ? ($authTokenData['token'] ?? null) : null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Ubisoft authentication failed (could not get token): '.$this->formatAuthResponse($authTokenRes));
        }

        $redirectUrlOpts['token'] = $token;
        $authorizeCallbackUrl = 'https://api.partners.ubisoft.com/v1/oauth/authorize/callback?'.http_build_query($redirectUrlOpts);
        $callbackRedirect = $this->curlRequest($authorizeCallbackUrl, null, $cookieFile, true);

        $callbackQuery = parse_url($callbackRedirect, PHP_URL_QUERY);
        if (! is_string($callbackQuery) || $callbackQuery === '') {
            throw new RuntimeException('Authorize callback query missing: '.$callbackRedirect);
        }
        parse_str($callbackQuery, $callbackOpts);
        $consentRedirectUrl = $callbackOpts['redirectUrl'] ?? null;
        if (! is_string($consentRedirectUrl) || $consentRedirectUrl === '') {
            throw new RuntimeException('Consent redirectUrl missing in callback redirect: '.$callbackRedirect);
        }
        $consentQuery = parse_url($consentRedirectUrl, PHP_URL_QUERY);
        $consentOpts = [];
        if (is_string($consentQuery) && $consentQuery !== '') {
            parse_str($consentQuery, $consentOpts);
        }
        $profileToken = $consentOpts['profile_token'] ?? null;

        unset($redirectUrlOpts['token']);
        if (is_string($profileToken)) {
            $redirectUrlOpts['profile_token'] = $profileToken;
        }
        $consentUrl = 'https://api.partners.ubisoft.com/v1/oauth/consents';
        $consentBody = (string) json_encode([
            'scopesConsented' => ['offline_access', 'openid', 'profile', 'email'],
            'isConsented' => true,
            'redirectUrl' => 'https://api.partners.ubisoft.com/v1/oauth/authorize/callback?'
                                 .http_build_query($redirectUrlOpts),
        ], JSON_THROW_ON_ERROR);
        $clientId = is_string($redirectUrlOpts['client_id'] ?? null) ? $redirectUrlOpts['client_id'] : '';
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
     * Cheap liveness probe for the stored session, mirroring FastAuth() in the
     * reference client (client/login.xaml.cs): one POST to bb/authenticate that
     * both validates the tokens and refreshes the cookie jar.
     *
     * Returns false only when we can positively tell the session is unusable.
     */
    public function verifySession(Account $account): bool
    {
        if (! $this->isAuthenticated($account)) {
            return false;
        }

        $cookieFile = $this->getCookieFile($account);
        if ($cookieFile === '' || ! is_file($cookieFile)) {
            // Tokens without a cookie jar cannot authenticate against the game.
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim((string) $account->bb_url, '/').'/authenticate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'DSOAUTHUSER' => (string) $account->dso_auth_user,
            'DSOAUTHTOKEN' => (string) $account->dso_auth_token,
        ]));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
            ['Content-Type: application/x-www-form-urlencoded'],
            self::BROWSER_HEADERS,
        ));

        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            Log::warning("[TsoAuth] Session probe for account #{$account->id} failed on transport level: {$error}");

            return false;
        }

        $alive = $status === 200 && ! str_contains($response, 'ERROR');

        Log::info("[TsoAuth] Session probe for account #{$account->id}: HTTP {$status}, alive=".($alive ? 'yes' : 'no'));

        return $alive;
    }

    /**
     * The only entry point application services should use: make sure the
     * account can talk to the game server, reusing the existing session
     * whenever possible and logging in only when it is actually dead.
     *
     * @throws Exception
     */
    public function ensureAuthenticated(Account $account): void
    {
        if (Cache::get(self::SESSION_OK_KEY.$account->id) === true) {
            return;
        }

        if ($this->verifySession($account)) {
            $this->markSessionVerified($account);

            return;
        }

        $this->login($account);
        $account->refresh();
    }

    private function markSessionVerified(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;

        Cache::put(self::SESSION_OK_KEY.$id, true, self::SESSION_OK_TTL);
    }

    private function forgetSessionVerified(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;

        Cache::forget(self::SESSION_OK_KEY.$id);
    }

    /**
     * Current session parameters in the same shape performLogin() returns.
     *
     * @return array<string, mixed>
     */
    private function sessionParams(Account $account): array
    {
        return [
            'dsoAuthToken' => (string) $account->dso_auth_token,
            'dsoAuthUser' => (string) $account->dso_auth_user,
            'bburl' => (string) $account->bb_url,
            'zoneId' => null,
            'nickName' => (string) $account->nickname,
        ];
    }

    /**
     * Which login flow to try first. Mirrors the cipMigrated switch of the
     * reference client, but learned from the last successful login instead of
     * being configured by hand.
     *
     * @return 'legacy'|'oauth'
     */
    private function preferredFlow(Account $account): string
    {
        return Cache::get(self::AUTH_FLOW_KEY.$account->id) === 'oauth' ? 'oauth' : 'legacy';
    }

    private function rememberFlow(Account $account, string $flow): void
    {
        Cache::put(self::AUTH_FLOW_KEY.$account->id, $flow, now()->addDays(30));
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
     *
     * @throws Exception
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

        if (empty($params) || ! isset($params['dsoAuthToken'])) {
            throw new RuntimeException('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

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
     *
     * @throws Exception
     */
    private function curlRequest(string $url, mixed $postData, string $cookieFile, bool $returnRedirect = false, ?array $headers = null): string
    {
        if ($url === '' || $cookieFile === '') {
            throw new RuntimeException('Invalid URL or cookie file.');
        }

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

        $defaultHeaders = self::BROWSER_HEADERS;

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
            throw new RuntimeException('cURL Error: '.$error);
        }

        if ($returnRedirect) {
            if (preg_match('/^Location:\s*([^\r\n]+)/mi', $response, $matches)) {
                return trim($matches[1]);
            }
            if (! empty($info['redirect_url'])) {
                return (string) $info['redirect_url'];
            }

            return $response;
        }

        return $response;
    }

    /**
     * Decode and format authentication error response into a human-readable string.
     */
    private function formatAuthResponse(string $response): string
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $title = isset($decoded['data']['title']) && is_string($decoded['data']['title'])
                    ? trim(strip_tags($decoded['data']['title']))
                    : '';
                $text = isset($decoded['data']['text']) && is_string($decoded['data']['text'])
                    ? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', "\r\n", "\r", "\n"], ' ', $decoded['data']['text'])))
                    : '';

                if ($title !== '' && $text !== '') {
                    return "{$title}: {$text}";
                }
                if ($text !== '') {
                    return $text;
                }
                if ($title !== '') {
                    return $title;
                }
            }

            $unescaped = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($unescaped)) {
                return $unescaped;
            }
        }

        return $response;
    }
}
