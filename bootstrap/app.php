<?php

use App\Exceptions\Contracts\HasApiPresentation;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->web(prepend: [
            SetLocale::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'cache.headers' => SetCacheHeaders::class,
        ]);

        $middleware->trustProxies(at: '*');

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
            if (config('app.debug')) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
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
