<?php

use App\Exceptions\Contracts\HasApiPresentation;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateTrustedHost;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Http\FlushEventsMiddleware;
use Sentry\Laravel\Http\SetRequestIpMiddleware;
use Sentry\Laravel\Http\SetRequestMiddleware;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(ValidateTrustedHost::class);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(SetRequestMiddleware::class);
        $middleware->append(SetRequestIpMiddleware::class);
        $middleware->append(FlushEventsMiddleware::class);

        $middleware->web(prepend: [
            ValidateTrustedHost::class,
            SetLocale::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'cache.headers' => SetCacheHeaders::class,
        ]);

        $trustedProxies = app()->has('config') ? config('app.trusted_proxies') : null;
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
        } else {
            $middleware->trustProxies(at: [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ]);
        }

        $middleware->trustHosts(at: function () {
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

            return array_values(array_unique($allowedHosts));
        });
        $middleware->redirectTo(
            guests: '/admin/login',
            users: '/admin'
        );

        $middleware->prepend(RequestId::class);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->render(function (HasApiPresentation $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->userMessage(),
                    'code' => $e->getCode() !== 0 ? $e->getCode() : $e->httpStatus(),
                ], $e->httpStatus());
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (app()->environment('testing') && ! str_contains($request->path(), 'test-unhandled-exception')) {
                    return null;
                }

                if ($e instanceof ValidationException
                    || $e instanceof HttpExceptionInterface
                    || $e instanceof AuthenticationException
                    || $e instanceof AuthorizationException) {
                    return null;
                }

                $sensitiveKeys = ['password', 'dso_auth_password', 'dso_auth_token', 'auth_token', 'token', 'secret', 'current_password', 'password_confirmation'];
                $redact = function (array $arr) use (&$redact, $sensitiveKeys): array {
                    foreach ($arr as $key => $val) {
                        if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                            $arr[$key] = '***REDACTED***';
                        } elseif (is_array($val)) {
                            $arr[$key] = $redact($val);
                        }
                    }

                    return $arr;
                };

                Log::error($e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'request' => $redact($request->all()),
                ]);

                return response()->json([
                    'message' => __('Server error occurred. Please try again later.'),
                    'code' => 500,
                ], 500);
            }

            return null;
        });
    })->create();
