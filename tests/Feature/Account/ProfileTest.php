<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['first_name' => 'Muthoni', 'timezone' => 'Africa/Nairobi']);
    $this->actingAs($this->user);
});

it('updates only the fields it is sent', function () {
    $this->patchJson('/api/user', ['why' => 'More energy for long beach days.', 'fasting_hours' => 16])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Muthoni')
        ->assertJsonPath('data.why', 'More energy for long beach days.')
        ->assertJsonPath('data.fasting_hours', 16);
});

it('follows the phone to a new time zone', function () {
    $this->patchJson('/api/user', ['timezone' => 'Europe/London'])->assertOk();

    expect($this->user->fresh()->timezone)->toBe('Europe/London');
});

it('clears "my why"', function () {
    $this->user->forceFill(['why' => 'Something'])->save();

    $this->patchJson('/api/user', ['why' => null])->assertOk()->assertJsonPath('data.why', null);
});

it('refuses invalid values', function (array $body, string $field) {
    $this->patchJson('/api/user', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'empty name' => [['first_name' => ''], 'first_name'],
    'not a time zone' => [['timezone' => 'EAT'], 'timezone'],
    'protocol too short' => [['fasting_hours' => 11], 'fasting_hours'],
    'protocol too long' => [['fasting_hours' => 21], 'fasting_hours'],
    'why too long' => [['why' => str_repeat('a', 501)], 'why'],
]);

it('never changes the email or password this way', function () {
    $this->patchJson('/api/user', ['email' => 'new@example.com', 'password' => 'another-password'])->assertOk();

    expect($this->user->fresh())
        ->email->toBe($this->user->email)
        ->password->toBe($this->user->password);
});
