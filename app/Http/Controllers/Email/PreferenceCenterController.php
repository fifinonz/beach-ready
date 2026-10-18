<?php

namespace App\Http\Controllers\Email;

use App\Consent\EmailConfirmation;
use App\Consent\EmailLinks;
use App\Consent\EmailTopic;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "Manage email preferences" link in every email. It works without
 * logging in; the route's `signed` middleware checks the link.
 */
class PreferenceCenterController extends Controller
{
    public function show(User $user): View
    {
        return view('email.preferences', [
            'user' => $user,
            'topics' => EmailTopic::cases(),
            'formUrl' => EmailLinks::preferences($user),
            'unsubscribeUrl' => EmailLinks::unsubscribe($user),
        ]);
    }

    public function update(Request $request, User $user, EmailConfirmation $confirmation): RedirectResponse
    {
        foreach (EmailTopic::cases() as $topic) {
            $user->{$topic->column()} = $request->boolean($topic->value);
        }

        $user->save();

        if (EmailTopic::anyOnFor($user)) {
            $confirmation->sendFirst($user);
        }

        return redirect()->to(EmailLinks::preferences($user))->with('saved', true);
    }
}
