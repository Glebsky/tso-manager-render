<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\MarketCacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpCacheHeaders
{
    private MarketCacheService $cacheService;

    public function __construct(MarketCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only handle GET/HEAD requests
        if (! $request->isMethodCacheable()) {
            return $next($request);
        }

        $serverId = $this->cacheService->resolveServerId($request->input('server_id'));
        $endpoint = $request->path();
        $params = $request->query();
        $cacheControl = $this->cacheControlValue($request);

        $etag = $this->cacheService->generateETag($serverId, $endpoint, $params);
        $dataVersion = $this->cacheService->dataVersion($serverId);

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            $response = new Response(null, 304);
            $response->headers->set('ETag', $etag);
            $response->headers->set('X-Data-Version', (string) $dataVersion);
            $response->headers->set('Cache-Control', $cacheControl);

            return $response;
        }

        $response = $next($request);

        $response->headers->set('ETag', $etag);
        $response->headers->set('X-Data-Version', (string) $dataVersion);
        $response->headers->set('Cache-Control', $cacheControl);

        return $response;
    }

    private function cacheControlValue(Request $request): string
    {
        if ($request->is('api/public/market/*')) {
            return 'public, max-age=60, stale-while-revalidate=240';
        }

        return 'private, max-age=60, stale-while-revalidate=240';
    }
}
