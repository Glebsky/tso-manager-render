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
