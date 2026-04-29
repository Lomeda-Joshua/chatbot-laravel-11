<?php

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

use App\Http\Middleware\validateApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Redirect to Unified login when authentication fails
        $middleware->redirectGuestsTo(function () {
            return rtrim(config('sso.project1.url'), '/') . '/login';
        });

        // Global middleware - Handle CORS first
        $middleware->use([
            HandleCors::class,
        ]);

        // API middleware
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Web middleware (session + csrf + cookies)
        $middleware->web(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);

        // Skip CSRF verification for specific API routes
        $middleware->validateCsrfTokens(except: [
            'api/sso/*',
        ]);

        // Aliases
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'api.key' => validateApiKey::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
