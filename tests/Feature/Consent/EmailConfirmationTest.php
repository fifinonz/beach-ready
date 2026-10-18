<?php

use App\Consent\EmailConfirmation;
use App\Mail\ConfirmEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-19 10:00:00');
    $this->user = User::factory()->create(['first_name' => 'Muthoni']);
});

describe('turning on email', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('starts with every topic off and the address unconfirmed', function () {
        $this->getJson('/api/settings/email')->assertExactJson(['data' => [
            'fasting_reminders' => false,
            'weekly_summary' => false,
            'product_news' => false,
            'email_confirmed' => false,
            'resend_available_in_seconds' => 0,
        ]]);
    });

    it('sends one confirmation email when fasting reminders are turned on', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true])
            ->assertOk()
            ->assertJsonPath('data.fasting_reminders', true)
            ->assertJsonPath('data.email_confirmed', false)
            ->assertJsonPath('data.resend_available_in_seconds', 60);

        Mail::assertQueued(ConfirmEmailMail::class, fn (ConfirmEmailMail $mail) => $mail->hasTo($this->user->email));
        Mail::assertQueuedCount(1);
    });

    it('never sends a second one by itself', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true]);
        $this->travel(10)->minutes();
        $this->patchJson('/api/settings/email', ['fasting_reminders' => false]);
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true]);
        $this->patchJson('/api/settings/email', ['weekly_summary' => true]);

        Mail::assertQueuedCount(1);
    });

    it('sends nothing when topics are only turned off', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => false, 'product_news' => false])
            ->assertOk();

        Mail::assertNothingQueued();
    });

    it('sends nothing to an address that is already confirmed', function () {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        $this->patchJson('/api/settings/email', ['fasting_reminders' => true])->assertOk();

        Mail::assertNothingQueued();
    });

    it('refuses a topic setting that is not true or false', function () {
        $this->patchJson('/api/settings/email', ['weekly_summary' => 'yes please'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weekly_summary');
    });
});

describe('signing up', function () {
    function signUp(array $overrides = []): TestResponse
    {
        return test()->postJson('/api/register', array_merge([
            'first_name' => 'Muthoni',
            'email' => 'muthoni@example.com',
            'password' => 'a-long-password',
            'timezone' => 'Africa/Nairobi',
        ], $overrides));
    }

    it('turns on fasting reminders and sends the confirmation when the box is ticked', function () {
        signUp(['email_reminders' => true])->assertCreated()->assertJsonPath('data.email_confirmed', false);

        expect(User::where('email', 'muthoni@example.com')->sole()->email_fasting_reminders)->toBeTrue();
        Mail::assertQueuedCount(1);
    });

    it('turns nothing on and sends nothing when the box is not ticked', function (array $checkbox) {
        signUp($checkbox)->assertCreated();

        expect(User::where('email', 'muthoni@example.com')->sole()->email_fasting_reminders)->toBeFalse();
        Mail::assertNothingQueued();
    })->with([
        'left out' => [[]],
        'unticked' => [['email_reminders' => false]],
    ]);
});

describe('asking for another confirmation email', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('sends one when none was sent in the last 60 seconds', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true]);
        $this->travel(60)->seconds();

        $this->postJson('/api/settings/email/confirmation')->assertAccepted();

        Mail::assertQueuedCount(2);
    });

    it('limits resends to one every 60 seconds', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true]);
        $this->travel(58)->seconds();

        $this->postJson('/api/settings/email/confirmation')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 2)
            ->assertJsonPath('retry_after_seconds', 2);

        Mail::assertQueuedCount(1);
    });

    it('counts the 60 seconds from the last resend, not the first email', function () {
        $this->patchJson('/api/settings/email', ['fasting_reminders' => true]);
        $this->travel(60)->seconds();
        $this->postJson('/api/settings/email/confirmation')->assertAccepted();
        $this->travel(30)->seconds();

        $this->postJson('/api/settings/email/confirmation')->assertTooManyRequests();

        Mail::assertQueuedCount(2);
    });

    it('refuses once the address is confirmed', function () {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        $this->postJson('/api/settings/email/confirmation')->assertConflict();

        Mail::assertNothingQueued();
    });

    it('refuses guests', function () {
        auth()->logout();

        $this->postJson('/api/settings/email/confirmation')->assertUnauthorized();
    });
});

describe('the confirmation link', function () {
    it('opens a page with a button, and confirms nothing by itself', function () {
        $this->get(EmailConfirmation::url($this->user))
            ->assertOk()
            ->assertSee('Yes, send me reminders')
            ->assertSee('method="post"', false);

        expect($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    });

    it('stays unconfirmed however often the link is opened, as a mail scanner would', function () {
        $url = EmailConfirmation::url($this->user);

        $this->get($url);
        $this->get($url);
        $this->get($url);

        expect($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    });

    it('posts the button back to the same signed link', function () {
        $url = EmailConfirmation::url($this->user);

        $this->get($url)->assertSee('action="'.e($url).'"', false);
    });

    it('confirms the address when the button is pressed', function () {
        $this->post(EmailConfirmation::url($this->user))
            ->assertOk()
            ->assertSee('Email confirmed. Reminders are on.');

        expect($this->user->fresh()->hasConfirmedEmail())->toBeTrue();
    });

    it('can be confirmed again without harm', function () {
        $url = EmailConfirmation::url($this->user);
        $this->post($url)->assertOk();
        $confirmedAt = $this->user->fresh()->email_verified_at;
        $this->travel(1)->hours();

        $this->post($url)->assertOk();

        expect($this->user->fresh()->email_verified_at)->toEqual($confirmedAt);
    });

    it('says it is already confirmed when opened again later', function () {
        $url = EmailConfirmation::url($this->user);
        $this->post($url);

        $this->get($url)->assertOk()->assertSee('Email confirmed. Reminders are on.');
    });

    it('works for 48 hours', function (string $method) {
        $url = EmailConfirmation::url($this->user);
        $this->travel(48)->hours();

        $this->call($method, $url)->assertOk();
    })->with(['GET', 'POST']);

    it('stops working after 48 hours', function (string $method) {
        $url = EmailConfirmation::url($this->user);
        $this->travelTo('2026-10-21 10:00:01'); // 48 hours and 1 second later.

        $this->call($method, $url)
            ->assertForbidden()
            ->assertSee('This link no longer works');

        expect($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    })->with(['GET', 'POST']);

    it('refuses a link whose signature was changed', function (string $method) {
        $url = EmailConfirmation::url($this->user).'0';

        $this->call($method, $url)->assertForbidden()->assertSee('This link no longer works');

        expect($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    })->with(['GET', 'POST']);

    it('refuses a link changed to confirm someone else', function (string $method) {
        $someoneElse = User::factory()->create();
        $url = str_replace("/email/confirm/{$this->user->id}/", "/email/confirm/{$someoneElse->id}/", EmailConfirmation::url($this->user));

        $this->call($method, $url)->assertForbidden();

        expect($someoneElse->fresh()->hasConfirmedEmail())->toBeFalse()
            ->and($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    })->with(['GET', 'POST']);

    it('refuses a link sent to the address before it was changed', function (string $method) {
        $url = EmailConfirmation::url($this->user);
        $this->user->update(['email' => 'new-address@example.com']);

        $this->call($method, $url)->assertForbidden()->assertSee('This link no longer works');

        expect($this->user->fresh()->hasConfirmedEmail())->toBeFalse();
    })->with(['GET', 'POST']);
});

describe('the email', function () {
    it('greets the user and links to the confirmation', function () {
        $mail = new ConfirmEmailMail($this->user);

        $mail->assertHasSubject('Confirm your Beach Ready reminders');
        $mail->assertSeeInText("One click and you're set, Muthoni");
        $mail->assertSeeInHtml('Yes, send me reminders');
        $mail->assertSeeInHtml('/email/confirm/'.$this->user->id.'/');
        $mail->assertSeeInText('This link works for 48 hours.');
    });
});
