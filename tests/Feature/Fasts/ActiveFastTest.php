<?php

use App\Models\Fast;
use App\Models\User;

beforeEach(function () {
    // 21:00 in Nairobi (UTC+3).
    $this->travelTo('2026-10-18 18:00:00');
    $this->user = User::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->actingAs($this->user);
});

describe('get the active fast', function () {
    it('returns the fast in progress', function () {
        $fast = Fast::factory()->for($this->user)->create();

        $this->getJson('/api/fasts/active')
            ->assertOk()
            ->assertJsonPath('data.id', $fast->id)
            ->assertJsonPath('data.duration_seconds', null);
    });

    it('returns null when there is no fast in progress', function () {
        Fast::factory()->for($this->user)->between('2026-10-17 18:00', '2026-10-18 10:00')->create();

        $this->getJson('/api/fasts/active')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    });

    it('never returns someone else\'s fast', function () {
        Fast::factory()->create();

        $this->getJson('/api/fasts/active')->assertExactJson(['data' => null]);
    });
});

describe('edit the start time', function () {
    it('moves the start time and keeps the goal the same length after it', function () {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T19:15'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-10-18T16:15:00Z')
            ->assertJsonPath('data.target_end_at', '2026-10-19T08:15:00Z')
            ->assertJsonPath('data.fasting_hours', 16);
    });

    it('refuses a start time in the future', function () {
        $fast = Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T21:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('started_at');

        expect($fast->fresh()->started_at)->toEqual($fast->started_at);
    });

    it('accepts a start time up to 24 hours ago', function () {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-17T21:00'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-10-17T18:00:00Z');
    });

    it('refuses a start time more than 24 hours ago', function () {
        $fast = Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-17T20:59'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['started_at' => "The start time can't be more than 24 hours ago."]);

        expect($fast->fresh()->started_at)->toEqual($fast->started_at);
    });

    it('refuses moving the start time before the previous fast ended', function () {
        // The previous fast ended at 18:00 Nairobi time.
        Fast::factory()->for($this->user)->between('2026-10-18 02:00', '2026-10-18 15:00')->create();
        $fast = Fast::factory()->for($this->user)->create(['started_at' => '2026-10-18 16:00:00']);

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T17:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['started_at' => "The start time can't be before your last fast ended."]);

        expect($fast->fresh()->started_at)->toEqual($fast->started_at);
    });

    it('accepts moving the start time back to when the previous fast ended', function () {
        Fast::factory()->for($this->user)->between('2026-10-18 02:00', '2026-10-18 15:00')->create();
        Fast::factory()->for($this->user)->create(['started_at' => '2026-10-18 16:00:00']);

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T18:00'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-10-18T15:00:00Z');
    });

    it('requires a start time', function () {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('started_at');
    });

    it('returns 404 when there is no fast in progress', function () {
        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T19:15'])
            ->assertNotFound();
    });

    it('never edits someone else\'s fast', function () {
        $theirs = Fast::factory()->create();

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T19:15'])
            ->assertNotFound();

        expect($theirs->fresh()->started_at)->toEqual($theirs->started_at);
    });
});

describe('end the fast', function () {
    it('ends a fast that reached its goal', function () {
        Fast::factory()->for($this->user)->create([
            'started_at' => '2026-10-18 01:00:00',
            'target_end_at' => '2026-10-18 17:00:00',
        ]);

        $this->postJson('/api/fasts/active/end', ['feeling' => 'great'])
            ->assertOk()
            ->assertJsonPath('data.ended_at', '2026-10-18T18:00:00Z')
            ->assertJsonPath('data.ended_early', false)
            ->assertJsonPath('data.feeling', 'great')
            ->assertJsonPath('data.duration_seconds', 17 * 3600);

        expect($this->user->fresh()->activeFast)->toBeNull();
    });

    it('saves a fast ended before its goal like any other', function () {
        Fast::factory()->for($this->user)->create([
            'started_at' => '2026-10-18 06:36:00',
            'target_end_at' => '2026-10-18 22:36:00',
        ]);

        $this->postJson('/api/fasts/active/end', ['feeling' => 'hungry'])
            ->assertOk()
            ->assertJsonPath('data.ended_early', true)
            ->assertJsonPath('data.duration_seconds', 11 * 3600 + 24 * 60);

        $this->getJson('/api/fasts')->assertJsonCount(1, 'data');
    });

    it('ends a fast that crossed midnight in the user\'s time zone', function () {
        // 20:30 yesterday to 12:30 today, Nairobi time.
        $this->travelTo('2026-10-18 09:30:00');
        Fast::factory()->for($this->user)->create([
            'started_at' => '2026-10-17 17:30:00',
            'target_end_at' => '2026-10-18 09:30:00',
        ]);

        $this->postJson('/api/fasts/active/end')
            ->assertOk()
            ->assertJsonPath('data.ended_early', false)
            ->assertJsonPath('data.duration_seconds', 16 * 3600);
    });

    it('accepts each feeling, or none', function (?string $feeling) {
        Fast::factory()->for($this->user)->create();

        $this->postJson('/api/fasts/active/end', ['feeling' => $feeling])
            ->assertOk()
            ->assertJsonPath('data.feeling', $feeling);
    })->with(['great', 'okay', 'hungry', 'unwell', null]);

    it('refuses an unknown feeling', function () {
        Fast::factory()->for($this->user)->create();

        $this->postJson('/api/fasts/active/end', ['feeling' => 'starving'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('feeling');
    });

    it('returns 404 when there is no fast in progress', function () {
        $this->postJson('/api/fasts/active/end')->assertNotFound();
    });

    it('never ends someone else\'s fast', function () {
        $theirs = Fast::factory()->create();

        $this->postJson('/api/fasts/active/end')->assertNotFound();

        expect($theirs->fresh()->isActive())->toBeTrue();
    });
});

describe('change the protocol', function () {
    it('moves the goal to the new number of hours after the start', function () {
        Fast::factory()->for($this->user)->create([
            'started_at' => '2026-10-18 10:00:00',
            'target_end_at' => '2026-10-19 02:00:00',
        ]);

        $this->patchJson('/api/fasts/active', ['fasting_hours' => 18])
            ->assertOk()
            ->assertJsonPath('data.protocol', '18:6')
            ->assertJsonPath('data.started_at', '2026-10-18T10:00:00Z')
            ->assertJsonPath('data.target_end_at', '2026-10-19T04:00:00Z');
    });

    it('can change the start time and the protocol together', function () {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['fasting_hours' => 14, 'started_at' => '2026-10-18T19:00'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-10-18T16:00:00Z')
            ->assertJsonPath('data.target_end_at', '2026-10-19T06:00:00Z');
    });

    it('refuses hours outside 12 to 20', function (int $hours) {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', ['fasting_hours' => $hours])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fasting_hours');
    })->with([11, 21]);

    it('requires a start time or a protocol', function () {
        Fast::factory()->for($this->user)->create();

        $this->patchJson('/api/fasts/active', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['started_at', 'fasting_hours']);
    });
});
