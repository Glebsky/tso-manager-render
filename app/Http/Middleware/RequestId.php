<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerId = $request->header('X-Request-Id');
        $id = (is_string($headerId) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $headerId) === 1)
            ? $headerId
            : (string) Str::uuid();

        Log::shareContext(['request_id' => $id]);
        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
