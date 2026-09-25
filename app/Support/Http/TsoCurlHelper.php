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
     * Initialize a cURL handle configured with game defaults (timeout, SSL verify, return transfer).
     *
     * @param  array<int, mixed>  $options
     */
    public static function init(string $url = '', array $options = []): CurlHandle
    {
        $ch = $url !== '' ? curl_init($url) : curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));

        if ($options !== []) {
            curl_setopt_array($ch, $options);
        }

        return $ch;
    }
}
