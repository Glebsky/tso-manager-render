<?php

namespace App\Services;

use App\Models\Account;
use Exception;
use Illuminate\Support\Facades\Storage;

class TsoAuthService
{
    /**
     * Server configurations per region.
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
            mkdir($dir, 0755, true);
        }

        return $dir.'/account_'.$account->id.'.txt';
    }

    /**
     * Authenticate the account and store tokens.
     *
     * @return array Auth tokens
     *
     * @throws Exception
     */
    public function login(Account $account): array
    {
        $region = $account->region;
        if (! isset(self::SERVERS[$region])) {
            throw new Exception("Invalid region: {$region}");
        }

        $server = self::SERVERS[$region];
        $cookieFile = $this->getCookieFile($account);

        // Clear old cookies to start fresh (like C# client does with new CookieCollection())
        if (file_exists($cookieFile)) {
            @unlink($cookieFile);
        }

        try {
            // Try CipSoft migrated login first (simple form POST, matching C# CipMigratedAuth)
            $params = $this->loginLegacy($account, $cookieFile, $server);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::warning("CipMigrated login failed for account {$account->id}: ".$e->getMessage());
            // If CipMigrated failed, try Ubisoft OAuth flow (C# CipAuth) as fallback
            try {
                $params = $this->loginOAuth($account, $cookieFile, $server);
            } catch (Exception $e2) {
                \Illuminate\Support\Facades\Log::warning("OAuth login also failed for account {$account->id}: ".$e2->getMessage());
                // Re-throw the original CipMigrated error as it's more likely relevant
                throw $e;
            }
        }

        // Update account with fresh tokens
        $account->update([
            'dso_auth_user' => $params['dsoAuthUser'],
            'dso_auth_token' => $params['dsoAuthToken'],
            'bb_url' => $params['bburl'],
            'nickname' => $params['nickName'],
            'status' => 'online',
        ]);

        return $params;
    }

    /**
     * Legacy form-based login (CipMigratedAuth).
     *
     * Exactly mirrors the C# client's CipMigratedAuth() flow:
     * 1. POST /ru/api/user/login with name + password
     * 2. POST /ru/главная-страница with start=1
     * 3. GET /ru/play → parse HTML for tokens
     *
     * The C# client uses BouncyCastle TLS for these requests (useBC=true),
     * which sends ONLY Host, Content-Type, Cookie, Connection: close headers.
     * No User-Agent, no Referer, no Accept headers.
     * It also manually handles redirects (AllowAutoRedirect = false).
     */
    public function loginLegacy(Account $account, string $cookieFile, array $server): array
    {
        // Step 1: POST Login
        $loginUrl = $server['domain'].str_replace('uplay', 'login', $server['uplay']);
        $loginRes = $this->cipMigratedRequest($loginUrl, [
            'name' => $account->username,
            'password' => $account->password,
        ], $cookieFile);

        \Illuminate\Support\Facades\Log::info('CipMigratedAuth login response: '.substr($loginRes, 0, 500));

        if (strpos($loginRes, 'OKAY') === false) {
            if (str_contains($loginRes, 'CAPTCHA') || str_contains($loginRes, 'captcha') || str_contains($loginRes, 'Captcha')) {
                throw new Exception(__('ui.auth.captcha_required'));
            }
            throw new Exception('Login failed: '.$loginRes);
        }

        // Step 2: POST Main page with start=1
        $mainUrl = $server['domain'].$server['main'];
        $this->cipMigratedRequest($mainUrl, ['start' => '1'], $cookieFile);

        // Step 3: GET Play page
        $playUrl = $server['domain'].$server['play'];
        $playHtml = $this->cipMigratedRequest($playUrl, null, $cookieFile);

        return $this->extractParams($playHtml);
    }

    /**
     * HTTP request that exactly mimics the C# PostSubmitter with useBC=true.
     *
     * BouncyCastle mode sends minimal headers:
     * - Host (automatic)
     * - Content-Type: application/x-www-form-urlencoded
     * - Cookie (from cookie jar)
     * - Connection: close
     * - Content-Length (for POST)
     *
     * NO User-Agent, NO Referer, NO Accept, NO Accept-Language.
     * Manual redirect handling (returns Location header content on redirect).
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            // Minimal headers matching BouncyCastle TLS mode
            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'Connection: close',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($isPost && $i === 0) {
                // Only POST on the first request, follow redirects as GET
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if ($error) {
                throw new Exception('cURL Error: '.$error);
            }

            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            // Check for redirect (3xx)
            if ($httpCode >= 300 && $httpCode < 400) {
                if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headerText, $matches)) {
                    $location = trim($matches[1]);
                    // Handle relative redirects
                    if (! str_starts_with($location, 'http')) {
                        $parsed = parse_url($currentUrl);
                        $location = $parsed['scheme'].'://'.$parsed['host'].$location;
                    }
                    $currentUrl = $location;

                    continue;
                }
            }

            // Non-redirect response — return body
            return $body;
        }

        throw new Exception('Too many redirects for URL: '.$url);
    }

    /**
     * Modern Ubisoft Connect OAuth login flow.
     */
    public function loginOAuth(Account $account, string $cookieFile, array $server): array
    {
        // 1. GET /oauth/start
        $oauthStartUrl = $server['domain'].'/oauth/start';
        $redirectUrl = $this->curlRequest($oauthStartUrl, null, $cookieFile, true);

        // GET the first redirect URL to establish cookies and get the final authorize URL
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

        // 2. POST to get Ubisoft accessToken
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

        // 3. POST to authenticate token using Basic Authorization header
        $authTokenUrl = 'https://api.partners.ubisoft.com/v1/profiles/authentication/token';
        $authTokenBody = json_encode(['rememberMe' => true]);
        $credentials = base64_encode(trim($account->username).':'.trim($account->password));
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

        // 4. GET authorize callback with the token to get the consents callback URL redirect
        $redirectUrlOpts['token'] = $token;
        $authorizeCallbackUrl = 'https://api.partners.ubisoft.com/v1/oauth/authorize/callback?'.http_build_query($redirectUrlOpts);
        $callbackRedirect = $this->curlRequest($authorizeCallbackUrl, null, $cookieFile, true);

        // Extract redirectUrl from callbackRedirect URL
        $callbackParts = parse_url($callbackRedirect);
        if (! isset($callbackParts['query'])) {
            throw new Exception('Authorize callback query missing: '.$callbackRedirect);
        }
        parse_str($callbackParts['query'], $callbackOpts);
        $consentRedirectUrl = $callbackOpts['redirectUrl'] ?? null;
        if (! $consentRedirectUrl) {
            throw new Exception('Consent redirectUrl missing in callback redirect: '.$callbackRedirect);
        }
        $consentRedirectParts = parse_url($consentRedirectUrl);
        parse_str($consentRedirectParts['query'] ?? '', $consentOpts);
        $profileToken = $consentOpts['profile_token'] ?? null;

        // 5. POST consents to get authorization code callback URL
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

        // 6. GET the consent callback redirect which redirects to the TSO login page
        $finalCallbackUrl = $this->curlRequest($consentRes, null, $cookieFile, true);

        // 7. Replace login with login2 and call it to set session cookies
        $login2Url = str_replace('/login?', '/login2?', $finalCallbackUrl);
        if ($login2Url === $finalCallbackUrl) {
            $login2Url = str_replace('/login', '/login2', $finalCallbackUrl);
        }
        $afterLogin2Url = $this->curlRequest($login2Url, null, $cookieFile, true);

        // Call the redirected page (usually /main)
        $this->curlRequest($afterLogin2Url, null, $cookieFile);

        // 8. Go to main homepage to ensure session cookies are set correctly
        $mainUrl = $server['domain'].$server['main'];
        $this->curlRequest($mainUrl, null, $cookieFile);

        // 9. Get the play page and extract tokens
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
     */
    public static function getServerConfig(string $region): ?array
    {
        return self::SERVERS[$region] ?? null;
    }

    /**
     * Extract flash vars from the play page HTML.
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

        // Save debug HTML in storage
        Storage::disk('local')->put('debug/play_page.html', $html);

        if (empty($params) || ! isset($params['dsoAuthToken'])) {
            throw new Exception('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

        Storage::disk('local')->put('debug/flash_vars.json', json_encode($params, JSON_PRETTY_PRINT));

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
     */
    private function curlRequest(string $url, $postData, string $cookieFile, bool $returnRedirect = false, ?array $headers = null): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

        $response = curl_exec($ch);
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
                return $info['redirect_url'];
            }

            return $response;
        }

        return $response;
    }
}
