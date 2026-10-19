<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-19 10:00:00');
    $this->user = User::factory()->create(['email' => 'muthoni@example.com', 'password' => 'the-old-password']);
});

describe('asking for a reset link', function () {
    it('sends the reset email to an account that exists', function () {
        $this->postJson('/api/forgot-password', ['email' => 'Muthoni@Example.com'])
            ->assertAccepted()
            ->assertJsonPath('message', "If there's an account for that email, we've sent a link to reset its password.");

        Mail::assertQueued(ResetPasswordMail::class, fn (ResetPasswordMail $mail) => $mail->hasTo('muthoni@example.com'));
    });

    it('gives exactly the same answer for an email with no account, and sends nothing', function () {
        $known = $this->postJson('/api/forgot-password', ['email' => 'muthoni@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        expect($unknown->status())->toBe($known->status())
            ->and($unknown->json())->toBe($known->json());
        Mail::assertQueuedCount(1);
    });

    it('sends even to an address that was never confirmed or unsubscribed from everything', function () {
        expect($this->user->hasConfirmedEmail())->toBeFalse();
        $this->user->forceFill(['email_fasting_reminders' => false, 'email_weekly_summary' => false, 'email_product_news' => false])->save();

        $this->postJson('/api/forgot-password', ['email' => 'muthoni@example.com'])->assertAccepted();

        Mail::assertQueued(ResetPasswordMail::class);
    });

    it('refuses something that is not an email address', function () {
        $this->postJson('/api/forgot-password', ['email' => 'not an email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    it('allows 5 requests an hour from one IP address', function () {
        foreach (range(1, 5) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
                ->postJson('/api/forgot-password', ['email' => "person{$n}@example.com"])->assertAccepted();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->postJson('/api/forgot-password', ['email' => 'muthoni@example.com'])->assertTooManyRequests();
    });

    it('allows 5 requests an hour for one email address, from any IP address', function () {
        foreach (range(1, 5) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$n}"])
                ->postJson('/api/forgot-password', ['email' => 'muthoni@example.com'])->assertAccepted();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->postJson('/api/forgot-password', ['email' => 'MUTHONI@example.com'])->assertTooManyRequests();
    });
});

describe('setting a new password', function () {
    function resetWith(string $token, array $overrides = []): TestResponse
    {
        return test()->postJson('/api/reset-password', array_merge([
            'token' => $token,
            'email' => 'muthoni@example.com',
            'password' => 'a-brand-new-password',
        ], $overrides));
    }

    it('changes the password and signs in on this device', function () {
        $token = Password::createToken($this->user);

        resetWith($token)->assertOk()->assertJsonPath('data.email', 'muthoni@example.com');

        $this->assertAuthenticatedAs($this->user->fresh());
        expect(Hash::check('a-brand-new-password', $this->user->fresh()->password))->toBeTrue()
            ->and(Hash::check('the-old-password', $this->user->fresh()->password))->toBeFalse();
    });

    it('signs the account out of every other session', function () {
        DB::table('sessions')->insert([
            ['id' => 'phone', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'laptop', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);
        $rememberToken = $this->user->remember_token;

        resetWith(Password::createToken($this->user))->assertOk();

        expect(DB::table('sessions')->whereIn('id', ['phone', 'laptop'])->exists())->toBeFalse()
            ->and($this->user->fresh()->remember_token)->not->toBe($rememberToken);
    });

    it('works only once', function () {
        $token = Password::createToken($this->user);
        resetWith($token)->assertOk();

        resetWith($token, ['password' => 'a-third-password-here'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'This reset link has expired or has already been used. Ask for a new one.']);
    });

    it('works for 60 minutes', function () {
        $token = Password::createToken($this->user);
        $this->travel(59)->minutes();

        resetWith($token)->assertOk();
    });

    it('expires after 60 minutes', function () {
        $token = Password::createToken($this->user);
        $this->travel(61)->minutes();

        resetWith($token)->assertUnprocessable()->assertJsonValidationErrors('token');
        expect(Hash::check('the-old-password', $this->user->fresh()->password))->toBeTrue();
    });

    it('refuses a token used with another account\'s email', function () {
        $token = Password::createToken($this->user);
        User::factory()->create(['email' => 'someone@example.com']);

        resetWith($token, ['email' => 'someone@example.com'])->assertUnprocessable();
    });

    it('requires a new password of at least 10 characters', function () {
        resetWith(Password::createToken($this->user), ['password' => '123456789'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });
});

describe('the reset email', function () {
    it('links to the reset screen with the token, and says how long it lasts', function () {
        $mail = new ResetPasswordMail($this->user, 'the-token');

        $mail->assertHasSubject('Reset your Beach Ready password');
        $mail->assertSeeInHtml(e(url('/reset-password/the-token').'?email=muthoni%40example.com'), false);
        $mail->assertSeeInText('This link works once, for 60 minutes.');
    });

    it('is an account email: no unsubscribe link or headers', function () {
        $mail = new ResetPasswordMail($this->user, 'the-token');

        // No headers() method, so no List-Unsubscribe headers, unlike BeachReadyMail.
        expect(method_exists($mail, 'headers'))->toBeFalse();
        $mail->assertDontSeeInHtml('Unsubscribe');
        $mail->assertDontSeeInText('Unsubscribe');
        $mail->assertSeeInText('someone asked to reset the password for your Beach Ready account');
    });
});
