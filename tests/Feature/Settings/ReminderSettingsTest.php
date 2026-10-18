<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('returns the reminder settings, with the defaults from the Reminders screen', function () {
    $this->getJson('/api/settings/reminders')->assertExactJson(['data' => [
        'before_goal' => true,
        'goal_reached' => true,
        'eating_window_closing' => false,
    ]]);
});

it('changes only the settings it is sent', function () {
    $this->patchJson('/api/settings/reminders', ['eating_window_closing' => true, 'before_goal' => false])
        ->assertOk()
        ->assertExactJson(['data' => [
            'before_goal' => false,
            'goal_reached' => true,
            'eating_window_closing' => true,
        ]]);

    expect($this->user->fresh())
        ->remind_before_goal->toBeFalse()
        ->remind_goal_reached->toBeTrue()
        ->remind_eating_window_closing->toBeTrue();
});

it('refuses a setting that is not true or false', function () {
    $this->patchJson('/api/settings/reminders', ['goal_reached' => 'sometimes'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('goal_reached');
});

it('ignores keys that are not reminder types', function () {
    $this->patchJson('/api/settings/reminders', ['password' => 'new-password', 'goal_reached' => false])
        ->assertOk();

    expect($this->user->fresh()->password)->toBe($this->user->password);
});

it('refuses guests', function (string $method) {
    auth()->logout();

    $this->json($method, '/api/settings/reminders')->assertUnauthorized();
})->with(['GET', 'PATCH']);
