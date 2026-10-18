<?php

use App\Models\User;

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Muthoni',
        'email' => 'muthoni@example.com',
        'password' => 'a-long-password',
        'timezone' => 'Africa/Nairobi',
    ], $overrides);
}

it('registers a user and logs them in', function () {
    $this->postJson('/api/register', validRegistration())
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Muthoni')
        ->assertJsonPath('data.email', 'muthoni@example.com')
        ->assertJsonPath('data.timezone', 'Africa/Nairobi')
        ->assertJsonMissingPath('data.password');

    $this->assertAuthenticated();
});

it('stores the email in lower case', function () {
    $this->postJson('/api/register', validRegistration(['email' => ' Muthoni@Example.COM ']))
        ->assertCreated();

    expect(User::sole()->email)->toBe('muthoni@example.com');
});

it('hashes the password', function () {
    $this->postJson('/api/register', validRegistration())->assertCreated();

    expect(User::sole()->password)->not->toBe('a-long-password');
});

it('requires a password of at least 10 characters', function () {
    $this->postJson('/api/register', validRegistration(['password' => '123456789']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->postJson('/api/register', validRegistration(['password' => '1234567890']))
        ->assertCreated();
});

it('requires a valid IANA time zone', function (string $timezone) {
    $this->postJson('/api/register', validRegistration(['timezone' => $timezone]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');
})->with(['Mars/Olympus', 'EAT', '+03:00', '']);

it('rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'muthoni@example.com']);

    $this->postJson('/api/register', validRegistration(['email' => 'MUTHONI@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('requires a first name, email, password and time zone', function () {
    $this->postJson('/api/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'email', 'password', 'timezone']);
});
