<?php

namespace App\Consent;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Links that go in every email. They're signed, so they work without
 * logging in and can't be changed to point at someone else. They don't
 * expire, because an unsubscribe link in an old email must still work.
 */
class EmailLinks
{
    public static function preferences(User $user): string
    {
        return URL::signedRoute('email.preferences', ['user' => $user->id]);
    }

    public static function unsubscribe(User $user): string
    {
        return URL::signedRoute('email.unsubscribe', ['user' => $user->id]);
    }
}
