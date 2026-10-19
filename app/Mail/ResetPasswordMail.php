<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends AccountMail
{
    public const LINK_VALID_MINUTES = 60;

    public function __construct(public User $user, public string $token) {}

    protected function recipient(): User
    {
        return $this->user;
    }

    protected function reason(): string
    {
        return "You're getting this because someone asked to reset the password for your Beach Ready account.";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Beach Ready password');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reset-password',
            text: 'mail.text.reset-password',
            with: [
                'firstName' => $this->user->first_name,
                // The reset screen in the Vue app, which posts the token back.
                'resetUrl' => url("/reset-password/{$this->token}").'?'.http_build_query(['email' => $this->user->email]),
                'linkValidMinutes' => self::LINK_VALID_MINUTES,
                ...$this->footer(),
            ],
        );
    }
}
