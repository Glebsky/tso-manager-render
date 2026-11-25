<?php

declare(strict_types=1);

namespace App\Support\Http;

use CurlHandle;

/**
 * Standard cURL helper for communicating with TSO game servers.
 *
 * Centralizes Flash client user agent, referer, SSL, and timeout settings (B-01, B-02).
 */
final class TsoCurlHelper
{
    public const string FLASH_USER_AGENT = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12';

    public const string FLASH_REFERER = 'http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf';

    /**
     * Standard Flash client headers expected by the game server.
     *
     * @return list<string>
     */
    public static function defaultFlashHeaders(): array
    {
        return [
            'User-Agent: '.self::FLASH_USER_AGENT,
            'Referer: '.self::FLASH_REFERER,
        ];
    }

    /**
     * Determine whether a load server URL uses the CipSoft Unified Load Server (/v1/territories/{t}/realms/{r}) API.
     */
    public static function isUnifiedLoadServerUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $path = (string) parse_url(trim($url), PHP_URL_PATH);

        return preg_match('#/v1/territories/[^/]+/realms/\d+/?$#i', $path) === 1;
    }

    /**
     * Determine if a load server URL (bb_url) is valid, secure (HTTPS), and points to an allowed TSO domain.
     */
    public static function isValidBbUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return false;
        }

        $scheme = strtolower($scheme);
        $host = strtolower($host);

        // In testing / local development environments, allow loopback HTTP for mocks
        if (app()->environment('testing', 'local') && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return in_array($scheme, ['http', 'https'], true);
        }

        if ($scheme !== 'https') {
            return false;
        }

        /** @var list<string> $allowedDomains */
        $allowedDomains = (array) config('game.allowed_bb_hosts', []);

        foreach ($allowedDomains as $allowedDomain) {
            $allowedDomain = strtolower(trim((string) $allowedDomain));
            if ($allowedDomain === '') {
                continue;
            }

            if ($host === $allowedDomain || str_ends_with($host, '.'.$allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize a cURL handle configured with game defaults (timeout, SSL verify, return transfer).
     *
     * @param  array<int, mixed>  $options
     */
    public static function init(string $url = '', array $options = []): CurlHandle
    {
        $ch = $url !== '' ? curl_init($url) : curl_init();

        $sslVerify = (bool) config('game.ssl_verify', true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));

        if ($options !== []) {
            curl_setopt_array($ch, $options);
        }

        return $ch;
    }
}
