<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sign-up form has a field people never see: the front end hides it
 * from sight, screen readers and the tab order. Bots that fill in every
 * field fill it in too. Their sign-up gets the same 201 a person would, so
 * the bot learns nothing, but no account is created, nobody is logged in
 * and no email is sent.
 */
class RejectHoneypot
{
    public const FIELD = 'website';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->filled(self::FIELD)) {
            return $next($request);
        }

        Log::info('Sign-up rejected by the honeypot.', ['ip' => $request->ip()]);

        return response()->json(['data' => [
            'id' => null,
            'first_name' => (string) $request->input('first_name'),
            'email' => (string) $request->input('email'),
            'timezone' => (string) $request->input('timezone'),
            'email_confirmed' => false,
        ]], 201);
    }
}
