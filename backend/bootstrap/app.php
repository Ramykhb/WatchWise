<?php

use App\Auth\Middleware\AuthenticateJwt;
use App\Auth\Middleware\AuthenticateOptional;
use App\Auth\Middleware\EnsureEmailVerified;
use App\Auth\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => AuthenticateJwt::class,
            'auth.optional' => AuthenticateOptional::class,
            'role' => EnsureRole::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
        // Resolve the account before per-user rate limits and route model binding.
        $middleware->prependToPriorityList(ThrottleRequests::class, AuthenticateJwt::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
