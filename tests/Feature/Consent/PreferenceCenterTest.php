<?php

use App\Consent\EmailLinks;
use App\Mail\ConfirmEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->receivingReminders()->create(['email_weekly_summary' => true]);
});

describe('the preference center', function () {
    it('shows each topic and whether it is on, without logging in', function () {
        $this->get(EmailLinks::preferences($this->user))
            ->assertOk()
            ->assertSeeInOrder(['Fasting reminders', 'Weekly summary', 'Product news'])
            ->assertSee('role="switch"', false);
    });

    it('saves the topics that are ticked and turns off the rest', function () {
        $url = EmailLinks::preferences($this->user);

        $this->post($url, ['product_news' => '1'])
            ->assertRedirect($url);

        expect($this->user->fresh())
            ->email_fasting_reminders->toBeFalse()
            ->email_weekly_summary->toBeFalse()
            ->email_product_news->toBeTrue();
    });

    it('sends the first confirmation email when an unconfirmed user turns a topic on', function () {
        $user = User::factory()->create();

        $this->post(EmailLinks::preferences($user), ['fasting_reminders' => '1']);

        Mail::assertQueued(ConfirmEmailMail::class);
    });

    it('refuses a link that was not signed by us', function () {
        $this->get("/email/preferences/{$this->user->id}")
            ->assertForbidden()
            ->assertSee('This link no longer works');
    });

    it('refuses a link changed to point at someone else', function () {
        $someoneElse = User::factory()->receivingReminders()->create();
        $url = str_replace("/preferences/{$this->user->id}", "/preferences/{$someoneElse->id}", EmailLinks::preferences($this->user));

        $this->post($url, [])->assertForbidden();

        expect($someoneElse->fresh()->email_fasting_reminders)->toBeTrue();
    });
});

describe('unsubscribing', function () {
    it('asks before unsubscribing when the link is opened', function () {
        $this->get(EmailLinks::unsubscribe($this->user))
            ->assertOk()
            ->assertSee('Unsubscribe from all emails?');

        expect($this->user->fresh()->email_fasting_reminders)->toBeTrue();
    });

    it('turns every topic off', function () {
        $this->post(EmailLinks::unsubscribe($this->user))
            ->assertOk()
            ->assertSee("You're unsubscribed", false);

        expect($this->user->fresh())
            ->email_fasting_reminders->toBeFalse()
            ->email_weekly_summary->toBeFalse()
            ->email_product_news->toBeFalse();
    });

    it('works as a one-click unsubscribe from a mail client, with no CSRF token', function () {
        // What Gmail and others send for List-Unsubscribe-Post (RFC 8058).
        $this->withMiddleware()
            ->post(EmailLinks::unsubscribe($this->user), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk();

        expect($this->user->fresh()->email_fasting_reminders)->toBeFalse();
    });

    it('keeps the address confirmed, so turning a topic back on needs no new email', function () {
        $this->post(EmailLinks::unsubscribe($this->user));

        expect($this->user->fresh()->hasConfirmedEmail())->toBeTrue();
    });

    it('refuses a tampered link', function () {
        $this->post(EmailLinks::unsubscribe($this->user).'x')->assertForbidden();

        expect($this->user->fresh()->email_fasting_reminders)->toBeTrue();
    });
});
