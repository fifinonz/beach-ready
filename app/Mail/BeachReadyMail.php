<?php

namespace App\Mail;

use App\Consent\EmailLinks;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;

/**
 * Every Beach Ready email extends this. It adds the one-click unsubscribe
 * headers (RFC 8058) and the data the shared footer needs: why the person is
 * getting the email, and links to the preference center and to unsubscribe.
 */
abstract class BeachReadyMail extends Mailable
{
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
