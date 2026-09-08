<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Account;
use Exception;
use RuntimeException;

class UbisoftConnectAuthClient
{
    /**
     * Browser fingerprint expected by the game backend.
     */
    private const array BROWSER_HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
        'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
    ];

    public function __construct(
        private readonly ?TsoPlayPageParser $parser = null,
    ) {}

    /**
     * Modern Ubisoft Connect OAuth login flow.
     *
     * @param  array{domain: string, uplay: string, main: string, play: string}  $server
     * @return array{dsoAuthToken: string, dsoAuthUser: string, bburl: string, zoneId: ?string, nickName: string}
     *
     * @throws Exception
     */
    public function login(Account $account, string $cookieFile, array $server): array
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
            $formattedOauthError = $this->formatAuthResponse($oauthTokenRes);
            if (stripos($formattedOauthError, 'captcha') !== false) {
                throw new RuntimeException((string) __('ui.auth.captcha_required'));
            }

            throw new RuntimeException('Ubisoft login failed (could not get oauthToken): '.$formattedOauthError);
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
            if (is_array($authTokenData)) {
                $errCode = $authTokenData['errorCode'] ?? null;
                $httpCode = $authTokenData['httpCode'] ?? null;
                $message = (string) ($authTokenData['message'] ?? '');
                if ($errCode === 3 || $httpCode === 401 || stripos($message, 'Invalid credentials') !== false) {
                    throw new RuntimeException((string) __('ui.auth.invalid_credentials'));
                }
                if (stripos($message, 'captcha') !== false) {
                    throw new RuntimeException((string) __('ui.auth.captcha_required'));
                }
            }

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

        $parser = $this->parser ?? new TsoPlayPageParser;

        return $parser->parse($playHtml);
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
