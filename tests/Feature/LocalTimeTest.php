<?php

use App\Models\User;

it('converts a wall-clock time in the user\'s time zone to UTC', function (string $timezone, string $local, string $utc) {
    $user = new User(['timezone' => $timezone]);

    expect($user->localTimeToUtc($local)->toIso8601ZuluString())->toBe($utc);
})->with([
    'Nairobi, no daylight saving' => ['Africa/Nairobi', '2026-10-18T20:30', '2026-10-18T17:30:00Z'],
    'London in summer time' => ['Europe/London', '2026-07-01T20:30', '2026-07-01T19:30:00Z'],
    'London in winter time' => ['Europe/London', '2026-12-01T20:30', '2026-12-01T20:30:00Z'],
    'New York, day before the clocks go back' => ['America/New_York', '2026-10-31T20:30', '2026-11-01T00:30:00Z'],
]);

it('keeps an explicit offset instead of the user\'s time zone', function () {
    $user = new User(['timezone' => 'Africa/Nairobi']);

    expect($user->localTimeToUtc('2026-10-18T20:30:00+01:00')->toIso8601ZuluString())
        ->toBe('2026-10-18T19:30:00Z');
});
