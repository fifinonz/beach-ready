<?php

namespace App\Http\Controllers\Email;

use App\Consent\EmailConfirmation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * The "Yes, send me reminders" link. The route's `signed` middleware has
 * already checked the signature and the 48-hour expiry.
 */
class ConfirmEmailController extends Controller
{
    public function __invoke(User $user, string $hash): Response
    {
        if (! hash_equals(EmailConfirmation::hashOf($user), $hash)) {
            return response()->view('email.link-invalid', status: 403);
        }

        if (! $user->hasConfirmedEmail()) {
            $user->forceFill(['email_verified_at' => CarbonImmutable::now()])->save();
        }

        return response()->view('email.confirmed', ['firstName' => $user->first_name]);
    }
}
