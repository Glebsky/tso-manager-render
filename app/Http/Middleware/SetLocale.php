<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'ru', 'uk'];

        // 1. Try to read locale from 'app_locale' cookie
        $locale = $request->cookie('app_locale');

        // 2. If cookie not set or invalid, try to detect from Accept-Language
        if (! $locale || ! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported);
        }

        // 3. Set the application locale
        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        } else {
            $locale = config('app.locale', 'en');
            app()->setLocale($locale);
        }

        // 4. Queue the cookie if it's missing or different from request
        if ($request->cookie('app_locale') !== $locale) {
            cookie()->queue('app_locale', $locale, 60 * 24 * 365, '/', null, false, false);
        }

        return $next($request);
    }
}
