<?php

use App\Models\Fast;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-18 18:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('lists finished fasts, most recent first', function () {
    $older = Fast::factory()->for($this->user)->between('2026-10-15 18:00', '2026-10-16 10:00')->create();
    $newer = Fast::factory()->for($this->user)->between('2026-10-16 18:00', '2026-10-17 05:00')->create();

    $this->getJson('/api/fasts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.ended_early', true)
        ->assertJsonPath('data.0.duration_seconds', 11 * 3600)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.ended_early', false);
});

it('leaves out the fast in progress', function () {
    Fast::factory()->for($this->user)->create();

    $this->getJson('/api/fasts')->assertOk()->assertJsonCount(0, 'data');
});

it('leaves out other users\' fasts', function () {
    Fast::factory()->between('2026-10-16 18:00', '2026-10-17 10:00')->create();

    $this->getJson('/api/fasts')->assertOk()->assertJsonCount(0, 'data');
});

it('pages through history 20 at a time', function () {
    foreach (range(1, 25) as $daysAgo) {
        $start = now()->subDays($daysAgo);
        Fast::factory()->for($this->user)->between($start, $start->addHours(16))->create();
    }

    $this->getJson('/api/fasts')
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/fasts?page=2')->assertJsonCount(5, 'data');
});
