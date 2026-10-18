<?php

use App\Consent\EmailConfirmation;
use App\Jobs\SendReminder;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->receivingReminders()->create(['password' => 'a-long-password']);
    $this->fast = Fast::factory()->for($this->user)->create();
    $this->reminder = Reminder::factory()->for($this->fast)->create(['send_at' => now()->addHour()]);
    $this->actingAs($this->user);
});

it('removes the user and everything that belongs to them', function () {
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->deleteJson('/api/account', ['password' => 'a-long-password'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull()
        ->and(Fast::where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(Reminder::find($this->reminder->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('logs the user out', function () {
    $this->deleteJson('/api/account', ['password' => 'a-long-password'])->assertNoContent();

    $this->assertGuest('web');
});

it('sends nothing for a reminder that was already queued', function () {
    Mail::fake();
    $this->deleteJson('/api/account', ['password' => 'a-long-password']);

    SendReminder::dispatchSync($this->reminder->id);

    Mail::assertNothingSent();
});

it('requires the current password', function (?string $password) {
    $this->deleteJson('/api/account', ['password' => $password])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($this->user->id))->not->toBeNull()
        ->and(Reminder::find($this->reminder->id))->not->toBeNull();
})->with(['not-the-password', null]);

it('leaves other users alone', function () {
    $someoneElse = User::factory()->create();
    $theirFast = Fast::factory()->for($someoneElse)->create();

    $this->deleteJson('/api/account', ['password' => 'a-long-password'])->assertNoContent();

    expect($someoneElse->fresh())->not->toBeNull()
        ->and($theirFast->fresh())->not->toBeNull();
});

it('refuses guests', function () {
    auth()->logout();

    $this->deleteJson('/api/account', ['password' => 'a-long-password'])->assertUnauthorized();
});

it('discards a confirmation email queued just before the account was deleted', function () {
    // The real queue and the array mailer, not fakes: what matters is what
    // the worker does with the job once the user is gone.
    config(['queue.default' => 'database']);
    $user = User::factory()->create(['password' => 'a-long-password']);
    app(EmailConfirmation::class)->sendFirst($user);
    expect(DB::table('jobs')->count())->toBe(1);

    $this->actingAs($user)->deleteJson('/api/account', ['password' => 'a-long-password'])->assertNoContent();
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
});
