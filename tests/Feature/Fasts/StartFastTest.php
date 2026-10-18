<?php

use App\Models\Fast;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    // 21:00 in Nairobi (UTC+3).
    $this->travelTo('2026-10-18 18:00:00');
    $this->user = User::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->actingAs($this->user);
});

it('starts a fast now when no start time is given', function () {
    $this->postJson('/api/fasts', ['fasting_hours' => 16])
        ->assertCreated()
        ->assertJsonPath('data.protocol', '16:8')
        ->assertJsonPath('data.fasting_hours', 16)
        ->assertJsonPath('data.eating_hours', 8)
        ->assertJsonPath('data.started_at', '2026-10-18T18:00:00Z')
        ->assertJsonPath('data.target_end_at', '2026-10-19T10:00:00Z')
        ->assertJsonPath('data.ended_at', null)
        ->assertJsonPath('data.ended_early', false);

    expect($this->user->activeFast)->not->toBeNull();
});

it('supports the preset protocols and custom hours from 12 to 20', function (int $fastingHours, string $protocol) {
    $this->postJson('/api/fasts', ['fasting_hours' => $fastingHours])
        ->assertCreated()
        ->assertJsonPath('data.protocol', $protocol);
})->with([
    '14:10' => [14, '14:10'],
    '16:8' => [16, '16:8'],
    '18:6' => [18, '18:6'],
    'custom at the minimum' => [12, '12:12'],
    'custom 15 hours' => [15, '15:9'],
    'custom at the cap' => [20, '20:4'],
]);

it('refuses fasting hours outside 12 to 20', function (mixed $fastingHours) {
    $this->postJson('/api/fasts', ['fasting_hours' => $fastingHours])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fasting_hours');

    expect(Fast::count())->toBe(0);
})->with([11, 1, 0, -1, 21, 24, 12.5, 'sixteen', null]);

it('reads a start time without an offset in the user\'s time zone', function () {
    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => '2026-10-18T20:30'])
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-10-18T17:30:00Z')
        ->assertJsonPath('data.target_end_at', '2026-10-19T09:30:00Z');
});

it('takes a start time with an offset as it is', function () {
    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => '2026-10-18T17:30:00Z'])
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-10-18T17:30:00Z');
});

it('refuses a start time in the future', function (string $startedAt) {
    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => $startedAt])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['started_at' => "The start time can't be in the future."]);
})->with([
    'local time, one minute ahead' => '2026-10-18T21:01',
    'UTC, one minute ahead' => '2026-10-18T18:01:00Z',
]);

it('refuses a start time that is not a date', function () {
    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => 'not a date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('started_at');
});

it('refuses a second fast while one is in progress', function () {
    Fast::factory()->for($this->user)->create();

    $this->postJson('/api/fasts', ['fasting_hours' => 16])
        ->assertConflict()
        ->assertJsonPath('message', 'You already have a fast in progress.');

    expect($this->user->fasts()->count())->toBe(1);
});

it('starts a new fast once the last one has ended', function () {
    Fast::factory()->for($this->user)->between('2026-10-17 18:00', '2026-10-18 10:00')->create();

    $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
});

it('lets different users fast at the same time', function () {
    Fast::factory()->create();

    $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
});

it('has the database refuse a second active fast for the same user', function () {
    Fast::factory()->for($this->user)->create();

    Fast::factory()->for($this->user)->create();
})->throws(UniqueConstraintViolationException::class);

it('has the database refuse a fast outside 12 to 20 hours', function (int $fastingHours) {
    Fast::factory()->for($this->user)->create(['fasting_hours' => $fastingHours]);
})->throws(QueryException::class, 'fasts_fasting_hours_range')->with([11, 21]);
