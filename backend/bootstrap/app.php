<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: null,
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/*',
        ]);
        
        $middleware->alias([
            'mfa' => \App\Http\Middleware\EnsureMfaVerified::class,
            'raw_body' => \App\Http\Middleware\CaptureRawBody::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Controlled JSON exception responses for API
    })->create();

