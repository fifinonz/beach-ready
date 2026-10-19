<?php

use App\Mail\ConfirmEmailMail;
use App\Mail\ResetPasswordMail;
use App\Models\Fast;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Monday 26 October 2026, 09:00 in Nairobi.
    $this->travelTo('2026-10-26 06:00:00');
});

function tryTheDemo(string $timezone = 'Africa/Nairobi', string $ip = '203.0.113.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/demo', ['timezone' => $timezone]);
}

describe('trying the demo', function () {
    it('creates a fresh demo account and signs in', function () {
        tryTheDemo()
            ->assertCreated()
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.email_confirmed', false)
            ->assertJsonPath('data.timezone', 'Africa/Nairobi');

        $user = User::sole();
        expect($user->email)->toEndWith('@demo.invalid')
            ->and($user->is_demo)->toBeTrue()
            ->and($user->email_fasting_reminders)->toBeFalse();
        $this->assertAuthenticatedAs($user);
    });

    it('gives every visitor their own account', function () {
        tryTheDemo();
        auth()->logout();
        tryTheDemo();

        expect(User::where('is_demo', true)->count())->toBe(2)
            ->and(User::pluck('email')->unique())->toHaveCount(2);
    });

    it('fills in three weeks of history and a fast in progress', function () {
        tryTheDemo();
        $fasts = User::sole()->fasts()->orderBy('started_at')->get();
        $finished = $fasts->whereNotNull('ended_at')->values();

        expect($finished)->toHaveCount(17)
            ->and($fasts->whereNull('ended_at'))->toHaveCount(1)
            ->and($finished->where('ended_early', true)->count())->toBeGreaterThan(0)
            ->and($finished->whereNotNull('feeling')->count())->toBeGreaterThan(0)
            ->and($finished->first()->started_at->gte(now()->subDays(23)))->toBeTrue();

        // Realistic: no overlaps, nothing in the future, nothing under an hour.
        $fasts->each(function (Fast $fast, int $i) use ($fasts) {
            expect($fast->started_at->lte(now()))->toBeTrue();
            if ($i > 0) {
                expect($fast->started_at->gte($fasts[$i - 1]->ended_at))->toBeTrue();
            }
            if ($fast->ended_at) {
                expect($fast->durationInSeconds())->toBeGreaterThanOrEqual(3600);
            }
        });
    });

    it('has a streak that a single rest day kept going', function () {
        tryTheDemo();

        // 15 days back, minus the rest days 4 and 9 days ago.
        $this->getJson('/api/stats/week')->assertJsonPath('data.current_streak_days', 13);
    });

    it('includes a fast finished today once it would have ended, so this week is never empty', function () {
        // Monday, 18:00 in Nairobi: today's fast ended around midday.
        $this->travelTo('2026-10-26 15:00:00');

        tryTheDemo();

        $this->getJson('/api/stats/week')
            ->assertJsonPath('data.fasts_this_week', 1)
            ->assertJsonPath('data.current_streak_days', 14);
        $active = $this->getJson('/api/fasts/active')->json('data');
        $endedToday = User::sole()->fasts()->whereNotNull('ended_at')->latest('ended_at')->first();
        expect(CarbonImmutable::parse($active['started_at'])->gte($endedToday->ended_at))->toBeTrue();
    });

    it('shows the fast in progress about ten hours in', function () {
        tryTheDemo();

        $active = $this->getJson('/api/fasts/active')->json('data');
        expect($active['protocol'])->toBe('16:8')
            ->and(now()->diffInMinutes($active['started_at'], true))->toEqualWithDelta(624, 1);
    });

    it('builds the history in the visitor\'s own time zone', function () {
        tryTheDemo('America/New_York');

        $first = User::sole()->fasts()->orderBy('started_at')->first();
        expect($first->started_at->setTimezone('America/New_York')->format('H'))->toBe('20');
    });

    it('requires a real time zone', function () {
        tryTheDemo('Mars/Olympus')->assertUnprocessable()->assertJsonValidationErrors('timezone');

        expect(User::count())->toBe(0);
    });

    it('allows 10 demo accounts an hour from one IP address', function () {
        foreach (range(1, 10) as $n) {
            tryTheDemo()->assertCreated();
            auth()->logout();
        }

        tryTheDemo()->assertTooManyRequests();
        tryTheDemo(ip: '198.51.100.7')->assertCreated();
    });
});

describe('demo accounts never get email', function () {
    it('sends no confirmation when reminders are turned on', function () {
        Mail::fake();
        tryTheDemo();

        $this->patchJson('/api/settings/email', ['fasting_reminders' => true])->assertOk();
        $this->postJson('/api/settings/email/confirmation')->assertConflict();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    });

    it('sends no password reset', function () {
        Mail::fake();
        tryTheDemo();
        $email = User::sole()->email;
        auth()->logout();

        $this->postJson('/api/forgot-password', ['email' => $email])->assertAccepted();

        Mail::assertNotQueued(ResetPasswordMail::class);
    });

    it('stops any email to the demo domain at the moment of sending, whatever queued it', function () {
        tryTheDemo();
        $demo = User::sole();
        $real = User::factory()->create();

        Mail::to($demo)->send(new ConfirmEmailMail($demo));
        Mail::to($real)->send(new ConfirmEmailMail($real));

        $sentTo = collect(Mail::mailer('array')->getSymfonyTransport()->messages())
            ->flatMap(fn ($message) => $message->getOriginalMessage()->getTo())
            ->map(fn ($address) => $address->getAddress());
        expect($sentTo->all())->toBe([$real->email]);
    });
});

describe('cleaning up', function () {
    it('deletes demo accounts more than 24 hours old, with everything they had', function () {
        tryTheDemo();
        $old = User::sole();
        DB::table('sessions')->insert(['id' => 'demo-session', 'user_id' => $old->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        auth()->logout();

        $this->travel(24 * 60 + 1)->minutes();
        tryTheDemo();
        $new = User::where('id', '!=', $old->id)->sole();
        $real = User::factory()->create(['created_at' => now()->subYear()]);

        $this->artisan('demo:prune')->expectsOutput('Deleted 1 demo accounts.')->assertSuccessful();

        expect(User::find($old->id))->toBeNull()
            ->and(Fast::where('user_id', $old->id)->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('id', 'demo-session')->exists())->toBeFalse()
            ->and(User::find($new->id))->not->toBeNull()
            ->and(User::find($real->id))->not->toBeNull();
    });

    it('can delete every demo account straight away, but never real ones', function () {
        tryTheDemo();
        auth()->logout();
        $real = User::factory()->create();

        $this->artisan('demo:prune --all')->expectsOutput('Deleted 1 demo accounts.')->assertSuccessful();

        expect(User::where('is_demo', true)->exists())->toBeFalse()
            ->and(User::find($real->id))->not->toBeNull();
    });

    it('runs every hour, on one server', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'demo:prune'));

        expect($event->expression)->toBe('0 * * * *')
            ->and($event->onOneServer)->toBeTrue();
    });
});
