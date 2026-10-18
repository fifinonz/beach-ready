<?php

namespace App\Mail;

use App\Consent\EmailLinks;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Every Beach Ready email extends this. It adds the one-click unsubscribe
 * headers (RFC 8058) and the data the shared footer needs: why the person is
 * getting the email, and links to the preference center and to unsubscribe.
 */
abstract class BeachReadyMail extends Mailable
{
    // A queued email stores only the IDs of its models and loads them when
    // it's sent, so it never goes to an account deleted in the meantime.
    use SerializesModels;

    /**
     * If its user was deleted before it was sent, the queued email is
     * dropped quietly. It isn't a failure, so it shouldn't raise a
     * failed-job alert.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The person this email is for.
     */
    abstract protected function recipient(): User;

    /**
     * Shown in the footer: "You're getting this because…".
     */
    abstract protected function reason(): string;

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.EmailLinks::unsubscribe($this->recipient()).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    protected function footer(): array
    {
        return [
            'footerReason' => $this->reason(),
            'preferencesUrl' => EmailLinks::preferences($this->recipient()),
            'unsubscribeUrl' => EmailLinks::unsubscribe($this->recipient()),
            'postalAddress' => config('mail.postal_address'),
        ];
    }
}
