<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api', 


    )
    ->withMiddleware(function (Middleware $middleware) {
                $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            // Ensure the request expects a JSON response, common in Sanctum/Axios setups
            if ($request->expectsJson()) {
                return response()->json([
                    'timestamp' => now()->format('Y-m-d H:i:s O'), 
                    'status'    => 401,
                    'error'     => 'Unauthorized',
                    'path'      => '/' . ltrim($request->path(), '/') 
                ], 401);
            }
        });
    })->create();
