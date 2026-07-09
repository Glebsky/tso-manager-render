<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Storage;
use Exception;

class TsoAuthService
{
    /**
     * Server configurations per region.
     */
    private const SERVERS = [
        'de'  => ['domain' => 'https://www.diesiedleronline.de',       'uplay' => '/de/api/user/uplay', 'main' => '/de/startseite',                                             'play' => '/de/spielen'],
        'us'  => ['domain' => 'https://www.thesettlersonline.net',     'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
        'en'  => ['domain' => 'https://www.thesettlersonline.com',     'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
        'fr'  => ['domain' => 'https://www.thesettlersonline.fr',      'uplay' => '/fr/api/user/uplay', 'main' => '/fr/page-de-d%C3%A9marrage',                                 'play' => '/fr/jouer'],
        'ru'  => ['domain' => 'https://www.thesettlersonline.ru',      'uplay' => '/ru/api/user/uplay', 'main' => '/ru/%D0%B3%D0%BB%D0%B0%D0%B2%D0%BD%D0%B0%D1%8F-%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0', 'play' => '/ru/play'],
        'pl'  => ['domain' => 'https://www.thesettlersonline.pl',      'uplay' => '/pl/api/user/uplay', 'main' => '/pl/strona-g%C5%82%C3%B3wna',                                'play' => '/pl/graj'],
        'es2' => ['domain' => 'https://www.thesettlersonline.es',      'uplay' => '/es/api/user/uplay', 'main' => '/es/p%C3%A1gina-de-inicio',                                  'play' => '/es/jugar'],
        'es'  => ['domain' => 'https://www.juego-thesettlersonline.com', 'uplay' => '/es/api/user/uplay', 'main' => '/es/p%C3%A1gina-de-inicio',                                'play' => '/es/jugar'],
        'nl'  => ['domain' => 'https://www.thesettlersonline.nl',      'uplay' => '/nl/api/user/uplay', 'main' => '/nl/homepage',                                               'play' => '/nl/play'],
        'cz'  => ['domain' => 'https://www.thesettlersonline.cz',      'uplay' => '/cz/api/user/uplay', 'main' => '/cs/domovsk%C3%A1-str%C3%A1nka',                              'play' => '/cs/play'],
        'pt'  => ['domain' => 'https://www.thesettlersonline.com.br',  'uplay' => '/pt/api/user/uplay', 'main' => '/pt/p%C3%A1gina-inicial',                                    'play' => '/pt/jogar'],
        'it'  => ['domain' => 'https://www.thesettlersonline.it',      'uplay' => '/it/api/user/uplay', 'main' => '/it/homepage',                                               'play' => '/it/gioca'],
        'el'  => ['domain' => 'https://www.thesettlersonline.gr',      'uplay' => '/el/api/user/uplay', 'main' => '/el/%CE%B1%CF%81%CF%87%CE%B9%CE%BA%CE%AE-%CF%83%CE%B5%CE%BB%CE%AF%CE%B4%CE%B1', 'play' => '/el/play'],
        'ro'  => ['domain' => 'https://www.thesettlersonline.ro',      'uplay' => '/ro/api/user/uplay', 'main' => '/ro/pagina-de-start',                                        'play' => '/ro/play'],
        'ts'  => ['domain' => 'https://www.tsotesting.com',            'uplay' => '/en/api/user/uplay', 'main' => '/en/homepage',                                               'play' => '/en/play'],
    ];

    /**
     * Get the cookie file path for a given account.
     */
    public function getCookieFile(Account $account): string
    {
        $dir = storage_path('app/cookies');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/account_' . $account->id . '.txt';
    }

    /**
     * Authenticate the account and store tokens.
     *
     * @return array Auth tokens
     * @throws Exception
     */
    public function login(Account $account): array
    {
        $region = $account->region;
        if (!isset(self::SERVERS[$region])) {
            throw new Exception("Invalid region: {$region}");
        }

        $server     = self::SERVERS[$region];
        $cookieFile = $this->getCookieFile($account);

        // 1. POST Login
        $loginUrl = $server['domain'] . str_replace('uplay', 'login', $server['uplay']);
        $loginRes = $this->curlRequest($loginUrl, [
            'name'     => $account->username,
            'password' => $account->password,
        ], $cookieFile);

        if (strpos($loginRes, 'OKAY') === false) {
            throw new Exception("Login failed: " . $loginRes);
        }

        // 2. POST Main
        $mainUrl = $server['domain'] . $server['main'];
        $this->curlRequest($mainUrl, ['start' => '1'], $cookieFile);

        // 3. GET Play
        $playUrl  = $server['domain'] . $server['play'];
        $playHtml = $this->curlRequest($playUrl, null, $cookieFile);

        $params = $this->extractParams($playHtml);

        // Update account with fresh tokens
        $account->update([
            'dso_auth_user'  => $params['dsoAuthUser'],
            'dso_auth_token' => $params['dsoAuthToken'],
            'bb_url'         => $params['bburl'],
            'nickname'       => $params['nickName'],
            'status'         => 'online',
        ]);

        return $params;
    }

    /**
     * Check if the account already has valid tokens.
     */
    public function isAuthenticated(Account $account): bool
    {
        return !empty($account->dso_auth_token) && !empty($account->dso_auth_user) && !empty($account->bb_url);
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

        if (empty($params) || !isset($params['dsoAuthToken'])) {
            throw new Exception('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

        Storage::disk('local')->put('debug/flash_vars.json', json_encode($params, JSON_PRETTY_PRINT));

        return [
            'dsoAuthToken' => $params['dsoAuthToken'],
            'dsoAuthUser'  => $params['dsoAuthUser'],
            'bburl'        => $params['bb'],
            'zoneId'       => $params['zoneID'] ?? null,
            'nickName'     => $nickName,
        ];
    }

    /**
     * Perform a cURL request with cookie support.
     */
    private function curlRequest(string $url, ?array $postData, string $cookieFile): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        return $response;
    }
}
