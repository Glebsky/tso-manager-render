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
        $response = $next($request);

        // Only handle GET/HEAD requests
        if (! $request->isMethodCacheable()) {
            return $response;
        }

        $serverId = (string) ($request->input('server_id') ?? 'ru');
        $endpoint = $request->path();
        $params = $request->query();

        $etag = $this->cacheService->generateETag($serverId, $endpoint, $params);
        $dataVersion = $this->cacheService->dataVersion($serverId);

        $response->headers->set('ETag', $etag);
        $response->headers->set('X-Data-Version', (string) $dataVersion);
        $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            $response->setStatusCode(304);
            $response->setContent(null);
        }

        return $response;
    }
}
