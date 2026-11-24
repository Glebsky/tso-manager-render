<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

final readonly class TsoPlayPageParser
{
    /**
     * Extract flash vars and nickname from the play page HTML.
     *
     * @return array{dsoAuthToken: string, dsoAuthUser: string, bburl: string, zoneId: ?string, nickName: string}
     */
    public function parse(string $html): array
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

        $dsoAuthToken = $params['dsoAuthToken'] ?? null;
        if (! is_string($dsoAuthToken) || $dsoAuthToken === '') {
            throw new RuntimeException('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

        $dsoAuthUser = isset($params['dsoAuthUser']) && is_scalar($params['dsoAuthUser'])
            ? (string) $params['dsoAuthUser']
            : '';

        $bburl = isset($params['bb']) && is_scalar($params['bb'])
            ? (string) $params['bb']
            : '';

        // If 'bb' is not explicitly present, extract from chat server host
        // (e.g. "http://r02-chat.thesettlersonline.ru:80" -> "https://r02-ls.thesettlersonline.ru/")
        if ($bburl === '' && ! empty($params['chatS']) && is_string($params['chatS'])) {
            if (preg_match('#^https?://r(\d+)-chat\.([^/:]+)#i', $params['chatS'], $chatMatches)) {
                $bburl = sprintf('https://r%02d-ls.%s/', (int) $chatMatches[1], $chatMatches[2]);
            }
        }

        // Fallback: derive from 'realm' and 'portal'
        // (e.g. realm=2, portal="https://www.thesettlersonline.ru" -> "https://r02-ls.thesettlersonline.ru/")
        if ($bburl === '' && ! empty($params['realm']) && is_scalar($params['realm']) && ! empty($params['portal']) && is_scalar($params['portal'])) {
            $portalHost = parse_url((string) $params['portal'], PHP_URL_HOST);
            if (is_string($portalHost) && $portalHost !== '') {
                $domain = preg_replace('/^www\./i', '', $portalHost);
                $bburl = sprintf('https://r%02d-ls.%s/', (int) $params['realm'], $domain);
            }
        }

        $zoneId = isset($params['zoneID']) && is_scalar($params['zoneID'])
            ? (string) $params['zoneID']
            : null;

        return [
            'dsoAuthToken' => $dsoAuthToken,
            'dsoAuthUser' => $dsoAuthUser,
            'bburl' => $bburl,
            'zoneId' => $zoneId,
            'nickName' => $nickName,
        ];
    }
}
