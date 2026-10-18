<?php

use App\Models\Fast;
use App\Models\User;

beforeEach(function () {
    // Wednesday 21 October 2026, 12:00 in Nairobi (UTC+3). The week began on
    // Monday 19 October at 00:00 Nairobi time, which is Sunday 21:00 UTC.
    $this->travelTo('2026-10-21 09:00:00');
    $this->user = User::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->actingAs($this->user);
});

function finishedFast(User $user, string $startedAt, string $endedAt): Fast
{
    return Fast::factory()->for($user)->between($startedAt, $endedAt)->create();
}

it('counts this week\'s fasts and their average length', function () {
    finishedFast($this->user, '2026-10-19 05:00', '2026-10-19 21:00'); // 16h
    finishedFast($this->user, '2026-10-20 05:00', '2026-10-20 19:00'); // 14h, ended early

    $this->getJson('/api/stats/week')
        ->assertOk()
        ->assertJsonPath('data.fasts_this_week', 2)
        ->assertJsonPath('data.average_fast_seconds', 15 * 3600);
});

it('returns zero fasts and no average for an empty week', function () {
    $this->getJson('/api/stats/week')->assertExactJson(['data' => [
        'fasts_this_week' => 0,
        'average_fast_seconds' => null,
        'current_streak_days' => 0,
    ]]);
});

it('leaves out the fast in progress', function () {
    Fast::factory()->for($this->user)->create();

    $this->getJson('/api/stats/week')->assertJsonPath('data.fasts_this_week', 0);
});

it('uses the user\'s week, not the UTC week, for a fast that crosses midnight', function () {
    // Ends 00:30 Monday in Nairobi, which is still Sunday in UTC. It belongs
    // to this week.
    finishedFast($this->user, '2026-10-18 05:30', '2026-10-18 21:30');
    // Ends 23:30 Sunday in Nairobi. It belongs to last week.
    finishedFast($this->user, '2026-10-17 04:30', '2026-10-18 20:30');

    $this->getJson('/api/stats/week')
        ->assertJsonPath('data.fasts_this_week', 1)
        ->assertJsonPath('data.average_fast_seconds', 16 * 3600);
});

it('counts consecutive days with a finished fast, including today', function () {
    finishedFast($this->user, '2026-10-18 02:00', '2026-10-18 18:00'); // Sunday
    finishedFast($this->user, '2026-10-19 02:00', '2026-10-19 18:00'); // Monday
    finishedFast($this->user, '2026-10-20 02:00', '2026-10-20 12:00'); // Tuesday, ended early
    finishedFast($this->user, '2026-10-20 17:00', '2026-10-21 08:00'); // Wednesday

    $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 4);
});

it('keeps the streak alive until the end of today', function () {
    finishedFast($this->user, '2026-10-19 02:00', '2026-10-19 18:00'); // Monday
    finishedFast($this->user, '2026-10-20 02:00', '2026-10-20 18:00'); // Tuesday

    $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 2);
});

it('restarts the streak after a day without a finished fast', function () {
    finishedFast($this->user, '2026-10-18 02:00', '2026-10-18 18:00'); // Sunday
    finishedFast($this->user, '2026-10-20 02:00', '2026-10-20 18:00'); // Tuesday

    $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 1);
});

it('has no streak when the last fast ended before yesterday', function () {
    finishedFast($this->user, '2026-10-18 02:00', '2026-10-18 18:00'); // Sunday

    $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 0);
});

it('counts streak days in the user\'s time zone', function () {
    // Ends 01:00 Tuesday in Nairobi, which is Monday in UTC.
    finishedFast($this->user, '2026-10-19 06:00', '2026-10-19 22:00');
    // Ends 10:00 Wednesday in Nairobi.
    finishedFast($this->user, '2026-10-20 15:00', '2026-10-21 07:00');

    $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 2);
});
