<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

/** Separates human-readable messages from legacy binary AMF transport results. */
final class TaskResultText
{
    public static function message(?string $response): ?string
    {
        if ($response === null || $response === '' || preg_match('//u', $response) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $response) === 1
            || str_contains($response, '_amf_response')) {
            return null;
        }
        $decoded = json_decode($response, true);
        if (is_array($decoded) && array_key_exists('errorCode', $decoded)) {
            return null;
        }

        return $response;
    }
}
