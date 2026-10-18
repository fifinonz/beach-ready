<?php

namespace App\Http\Controllers\Email;

use App\Consent\EmailConfirmation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The "Yes, send me reminders" link. Opening it only shows a button, because
 * mail scanners open links on their own; pressing the button (a POST)
 * confirms. The route's `signed` middleware checks the signature and the
 * 48-hour expiry on both, and each checks the address hash.
 */
class ConfirmEmailController extends Controller
{
    public function show(Request $request, User $user, string $hash): Response
    {
        if (! $this->linkMatchesAddress($user, $hash)) {
            return response()->view('email.link-invalid', status: 403);
        }

        if ($user->hasConfirmedEmail()) {
            return response()->view('email.confirmed', ['firstName' => $user->first_name]);
        }

        return response()->view('email.confirm', [
            'firstName' => $user->first_name,
            'formUrl' => $request->fullUrl(),
        ]);
    }

    public function store(User $user, string $hash): Response
    {
        if (! $this->linkMatchesAddress($user, $hash)) {
            return response()->view('email.link-invalid', status: 403);
        }

        if (! $user->hasConfirmedEmail()) {
            $user->forceFill(['email_verified_at' => CarbonImmutable::now()])->save();
        }

        return response()->view('email.confirmed', ['firstName' => $user->first_name]);
    }

    /**
     * The hash ties the link to the address it was sent to, so a link sent
     * before the address changed stops working.
     */
    private function linkMatchesAddress(User $user, string $hash): bool
    {
        return hash_equals(EmailConfirmation::hashOf($user), $hash);
    }
}
