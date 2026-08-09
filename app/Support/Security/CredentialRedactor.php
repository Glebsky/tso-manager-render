<?php

declare(strict_types=1);

namespace App\Support\Security;

use App\Models\Account;

class CredentialRedactor
{
    /**
     * Redact sensitive credentials (passwords, tokens, keys) from log messages or text.
     */
    public static function redact(string $message, ?Account $account = null): string
    {
        if ($message === '') {
            return '';
        }

        if ($account !== null) {
            try {
                $password = $account->password;
                if (! empty($password)) {
                    $message = str_replace((string) $password, '[REDACTED]', $message);
                }
            } catch (\Throwable $e) {
                // Ignore decryption failure on invalid account
            }

            try {
                $token = $account->dso_auth_token;
                if (! empty($token)) {
                    $message = str_replace((string) $token, '[REDACTED]', $message);
                }
            } catch (\Throwable $e) {
                // Ignore decryption failure on invalid account
            }
        }


        /** @var array<string, string> $patterns */
        $patterns = [
            '/(password=)[^\s&]+/i' => '$1[REDACTED]',
            '/("password"\s*:\s*")[^"]+(")/i' => '$1[REDACTED]$2',
            '/(\'password\'\s*=>\s*\')[^\']+(\')/i' => '$1[REDACTED]$2',
            '/("dsoAuthToken"\s*:\s*")[^"]+(")/i' => '$1[REDACTED]$2',
            '/("dso_auth_token"\s*:\s*")[^"]+(")/i' => '$1[REDACTED]$2',
            '/(dsoAuthToken=)[^\s&]+/i' => '$1[REDACTED]',
            '/(dso_auth_token=)[^\s&]+/i' => '$1[REDACTED]',
            '/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i' => '$1[REDACTED]',
            '/(Basic\s+)[A-Za-z0-9\+\/]+=*/i' => '$1[REDACTED]',
            '/(token=)[^\s&]+/i' => '$1[REDACTED]',
            '/("token"\s*:\s*")[^"]+(")/i' => '$1[REDACTED]$2',
        ];

        $redacted = preg_replace(array_keys($patterns), array_values($patterns), $message);

        return is_string($redacted) ? $redacted : $message;
    }

    /**
     * Redact sensitive credentials from log context arrays.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redactContext(array $context, ?Account $account = null): array
    {
        $sensitiveKeys = [
            'password',
            'dso_auth_token',
            'dsoauthtoken',
            'token',
            'access_token',
            'accesstoken',
            'secret',
            'authorization',
            'credentials',
        ];

        array_walk_recursive($context, function (&$value, $key) use ($sensitiveKeys, $account): void {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            } elseif (is_string($value)) {
                $value = static::redact($value, $account);
            }
        });

        return $context;
    }
}
