<?php

namespace App\Mail;

use App\Consent\EmailConfirmation;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmEmailMail extends BeachReadyMail
{
    public function __construct(public User $user) {}

    protected function recipient(): User
    {
        return $this->user;
    }

    protected function reason(): string
    {
        return "You're getting this because someone signed up for Beach Ready with this address.";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your Beach Ready reminders');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.confirm-email',
            text: 'mail.text.confirm-email',
            with: [
                'firstName' => $this->user->first_name,
                'confirmUrl' => EmailConfirmation::url($this->user),
                'linkValidHours' => EmailConfirmation::LINK_VALID_HOURS,
                ...$this->footer(),
            ],
        );
    }
}
