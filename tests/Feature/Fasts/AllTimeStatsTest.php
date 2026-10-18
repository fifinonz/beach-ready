<?php

use App\Models\Fast;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('counts finished fasts and finds the longest, ended early or not', function () {
    Fast::factory()->for($this->user)->between('2026-10-10 02:00', '2026-10-10 18:00')->create(); // 16h
    Fast::factory()->for($this->user)->between('2026-10-11 02:00', '2026-10-11 19:06')->create(); // 17h 06m
    Fast::factory()->for($this->user)->between('2026-10-12 02:00', '2026-10-12 08:00')->create(); // 6h, early
    Fast::factory()->for($this->user)->create(); // in progress: not counted

    $this->getJson('/api/stats/all-time')->assertExactJson(['data' => [
        'fasts_logged' => 3,
        'longest_fast_seconds' => 17 * 3600 + 6 * 60,
    ]]);
});

it('has no longest fast before the first one is finished', function () {
    $this->getJson('/api/stats/all-time')->assertExactJson(['data' => [
        'fasts_logged' => 0,
        'longest_fast_seconds' => null,
    ]]);
});

it('only counts the user\'s own fasts', function () {
    Fast::factory()->between('2026-10-10 02:00', '2026-10-10 18:00')->create();

    $this->getJson('/api/stats/all-time')->assertJsonPath('data.fasts_logged', 0);
});
