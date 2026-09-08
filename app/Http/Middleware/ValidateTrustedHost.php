<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateTrustedHost
{
    /**
     * Handle an incoming request and ensure the Host header matches allowed hosts.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedHosts = [
            '127.0.0.1',
            'localhost',
            'localhost:80',
            'localhost:443',
        ];

        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $parsedHost = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $allowedHosts[] = $parsedHost;
            }
        }

        $rawHost = $request->headers->get('HOST') ?? $request->getHost();
        // Remove port if present
        $hostOnly = (string) preg_replace('/:\d+$/', '', (string) $rawHost);

        $isAllowed = false;
        foreach ($allowedHosts as $pattern) {
            $patternOnly = (string) preg_replace('/:\d+$/', '', (string) $pattern);
            if (strcasecmp($hostOnly, $patternOnly) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            abort(400, sprintf('Untrusted Host "%s".', $rawHost));
        }

        return $next($request);
    }
}
