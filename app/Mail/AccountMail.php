<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Emails about the account itself, such as a password reset. Unlike
 * BeachReadyMail, they aren't marketing or reminders the person chose: they
 * go whether or not the address is confirmed, and have no unsubscribe link,
 * because they only ever answer something the person just did. See
 * decision 0009.
 */
abstract class AccountMail extends Mailable
{
    // Queued account emails store only the user's ID, and are dropped if the
    // account is deleted before they're sent.
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    /**
     * The person this email is for.
     */
    abstract protected function recipient(): User;

    /**
     * Shown in the footer: "You're getting this because…".
     */
    abstract protected function reason(): string;

    /**
     * @return array<string, string|null>
     */
    protected function footer(): array
    {
        return [
            'footerReason' => $this->reason(),
            'preferencesUrl' => null,
            'unsubscribeUrl' => null,
            'postalAddress' => config('mail.postal_address'),
        ];
    }
}
