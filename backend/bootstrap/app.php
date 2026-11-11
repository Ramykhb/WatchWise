<?php

use App\Auth\Middleware\AuthenticateJwt;
use App\Auth\Middleware\EnsureEmailVerified;
use App\Auth\Middleware\EnsureRole;
use App\Support\AccountLink;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\URL;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Which proxies to trust is read per request from config/trustedproxy.php (TRUSTED_PROXIES). Only the
        // client address and the scheme are taken from them: X-Forwarded-Host/-Port would let a caller choose the
        // host of URLs built from the request (signed email links), and the AWS ELB and RFC 7239 `Forwarded`
        // headers are never read.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->alias([
            'auth.jwt' => AuthenticateJwt::class,
            'role' => EnsureRole::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
        // Resolve the account, then require a verified email, before per-user rate limits and route model
        // binding: a guest gets 401, an unverified member 403, and neither spends a rate-limit slot.
        $middleware->prependToPriorityList(ThrottleRequests::class, AuthenticateJwt::class);
        $middleware->prependToPriorityList(ThrottleRequests::class, EnsureEmailVerified::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            // The emailed verification, email-change and account-deletion links: a browser opening one (GET) lands on
            // the public result page; the confirmation page's click (POST) gets the outcome as JSON.
            $link = AccountLink::forRoute($request->route()?->getName());

            if ($link !== null) {
                $expired = URL::hasCorrectSignature($request) && ! URL::signatureHasNotExpired($request);
                $outcome = $expired ? 'expired' : 'invalid';

                return $request->isMethod('POST')
                    ? response()->json(['outcome' => $outcome], $expired ? 410 : 403)
                    : redirect($link->resultPage($outcome));
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
