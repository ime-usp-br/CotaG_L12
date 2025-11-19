<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return route('login.local');
        });

        $middleware->redirectUsersTo(fn (Request $request) => route('lancamento'));

        // Example of other common middleware configurations that might exist:
        // $middleware->validateCsrfTokens(except: [
        //     'stripe/*',
        // ]);
        // $middleware->alias([
        //     'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is where custom exception handling might be configured.
    })->create();
