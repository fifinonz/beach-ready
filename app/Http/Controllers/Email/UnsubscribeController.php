<?php

namespace App\Http\Controllers\Email;

use App\Consent\EmailLinks;
use App\Consent\EmailTopic;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * "Unsubscribe from all emails". Opening the link only asks, because mail
 * scanners open links on their own. The POST does it, and is also what
 * mail clients send for one-click unsubscribe (RFC 8058), so it needs no
 * CSRF token or login; the signed link is the proof.
 */
class UnsubscribeController extends Controller
{
    public function show(User $user): View
    {
        return view('email.unsubscribe', ['formUrl' => EmailLinks::unsubscribe($user)]);
    }

    public function store(User $user): View
    {
        EmailTopic::unsubscribeFromAll($user);

        return view('email.unsubscribed', ['preferencesUrl' => EmailLinks::preferences($user)]);
    }
}
