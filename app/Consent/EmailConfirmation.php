<?php

namespace App\Consent;

use App\Mail\ConfirmEmailMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Double opt-in. An unconfirmed address gets exactly one confirmation email
 * on its own, plus any the user asks for, at most one every 60 seconds.
 * See decision 0006.
 */
class EmailConfirmation
{
    public const LINK_VALID_HOURS = 48;

    public const RESEND_AFTER_SECONDS = 60;

    /**
     * Sends the first confirmation email, if this address has never had one.
     * Called whenever the user turns on an email topic.
     */
    public function sendFirst(User $user): void
    {
        if ($user->hasConfirmedEmail()) {
            return;
        }

        // A conditional update, so two requests at once can't both send.
        $claimed = User::query()
            ->whereKey($user->id)
            ->whereNull('confirmation_sent_at')
            ->update(['confirmation_sent_at' => CarbonImmutable::now()]);

        if ($claimed) {
            $this->send($user);
        }
    }

    /**
     * Sends another confirmation email because the user asked for one.
     * Returns how many seconds to wait if the last one was too recent.
     */
    public function resend(User $user): ?int
    {
        $now = CarbonImmutable::now();

        $claimed = User::query()
            ->whereKey($user->id)
            ->where(fn ($query) => $query
                ->whereNull('confirmation_sent_at')
                ->orWhere('confirmation_sent_at', '<=', $now->subSeconds(self::RESEND_AFTER_SECONDS)))
            ->update(['confirmation_sent_at' => $now]);

        if (! $claimed) {
            return $this->secondsUntilResend($user->fresh(), $now);
        }

        $this->send($user);

        return null;
    }

    public function secondsUntilResend(User $user, CarbonImmutable $now): int
    {
        if ($user->confirmation_sent_at === null) {
            return 0;
        }

        $allowedAt = $user->confirmation_sent_at->addSeconds(self::RESEND_AFTER_SECONDS);

        return max(0, (int) ceil($now->diffInSeconds($allowedAt, false)));
    }

    /**
     * A signed link that expires after 48 hours. The hash ties it to the
     * address it was sent to, so it stops working if the address changes.
     */
    public static function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'email.confirm',
            CarbonImmutable::now()->addHours(self::LINK_VALID_HOURS),
            ['user' => $user->id, 'hash' => self::hashOf($user)],
        );
    }

    public static function hashOf(User $user): string
    {
        return sha1($user->email);
    }

    private function send(User $user): void
    {
        Mail::to($user)->queue(new ConfirmEmailMail($user));
    }
}
