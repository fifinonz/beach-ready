<?php

use App\Http\Middleware\RejectHoneypot;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Vue SPA authenticates with Sanctum's session cookies, not tokens.
        $middleware->statefulApi();

        // Mail clients send one-click unsubscribes (RFC 8058) with no CSRF
        // token. The signed link is the proof instead.
        $middleware->validateCsrfTokens(except: ['email/unsubscribe/*']);

        $middleware->alias(['honeypot' => RejectHoneypot::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An expired or tampered email link gets a page that explains it,
        // not a bare 403.
        $exceptions->render(fn (InvalidSignatureException $e, Request $request) => $request->is('email/*')
            ? response()->view('email.link-invalid', status: 403)
            : null);
    })->create();
