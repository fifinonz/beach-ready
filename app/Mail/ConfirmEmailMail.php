<?php

namespace App\Mail;

use App\Consent\EmailConfirmation;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmEmailMail extends Mailable
{
    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your Beach Ready reminders');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.confirm-email',
            with: [
                'firstName' => $this->user->first_name,
                'confirmUrl' => EmailConfirmation::url($this->user),
                'linkValidHours' => EmailConfirmation::LINK_VALID_HOURS,
            ],
        );
    }
}
