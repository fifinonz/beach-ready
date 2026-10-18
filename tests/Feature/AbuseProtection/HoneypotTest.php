<?php

use App\Mail\ConfirmEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Mail::fake());

function signUpWith(array $extra): TestResponse
{
    return test()->postJson('/api/register', array_merge([
        'first_name' => 'Muthoni',
        'email' => 'muthoni@example.com',
        'password' => 'a-long-password',
        'timezone' => 'Africa/Nairobi',
        'email_reminders' => true,
    ], $extra));
}

it('looks like a normal sign-up to a bot that fills in the hidden field', function () {
    signUpWith(['favourite_colour' => 'blue'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'muthoni@example.com')
        ->assertJsonPath('data.email_confirmed', false);
});

it('creates no account, logs nobody in and sends no email', function () {
    signUpWith(['favourite_colour' => 'blue']);

    expect(User::count())->toBe(0);
    $this->assertGuest();
    Mail::assertNothingQueued();
});

it('rejects a bot even when the rest of the form is invalid, so errors give nothing away', function () {
    $this->postJson('/api/register', ['favourite_colour' => 'x', 'password' => 'short'])
        ->assertCreated();

    expect(User::count())->toBe(0);
});

it('lets people through when the hidden field is left empty', function (array $extra) {
    signUpWith($extra)->assertCreated();

    expect(User::count())->toBe(1);
    $this->assertAuthenticated();
    Mail::assertQueued(ConfirmEmailMail::class);
})->with([
    'not sent' => [[]],
    'empty' => [['favourite_colour' => '']],
]);

it('logs each rejection, so a real person caught by mistake can be spotted', function () {
    Log::spy();

    signUpWith(['favourite_colour' => 'blue']);

    Log::shouldHaveReceived('info')->once()->with('Sign-up rejected by the honeypot.', Mockery::type('array'));
});
