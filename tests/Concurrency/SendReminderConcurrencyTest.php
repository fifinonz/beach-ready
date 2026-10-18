<?php

use App\Jobs\SendReminder;
use App\Models\Fast;
use App\Models\Reminder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-19 10:00:00');

    // A second connection to the same database stands in for another worker.
    config(['database.connections.other_worker' => config('database.connections.pgsql')]);
    $this->otherWorker = DB::connection('other_worker');
});

afterEach(function () {
    $this->otherWorker->rollBack();
    DB::purge('other_worker');
    DB::statement('RESET lock_timeout');
    DB::statement('TRUNCATE users, fasts, reminders RESTART IDENTITY CASCADE');
});

it('makes a second worker wait while the first is sending, then skip', function () {
    $reminder = Reminder::factory()->for(Fast::factory())->create(['send_at' => now()]);

    // The other worker has locked the reminder and is part-way through sending.
    $this->otherWorker->beginTransaction();
    $this->otherWorker->select('SELECT id FROM reminders WHERE id = ? FOR UPDATE', [$reminder->id]);

    // This worker can't get past the lock. A short timeout stands in for the
    // wait, so the test doesn't hang.
    DB::statement("SET lock_timeout = '200ms'");
    expect(fn () => SendReminder::dispatchSync($reminder->id))
        ->toThrow(QueryException::class, 'lock timeout');
    Mail::assertNothingSent();

    // The other worker finishes its send.
    $this->otherWorker->update("UPDATE reminders SET status = 'sent', sent_at = now() WHERE id = ?", [$reminder->id]);
    $this->otherWorker->commit();

    // Now this worker gets the lock, sees it was sent, and sends nothing.
    SendReminder::dispatchSync($reminder->id);
    Mail::assertNothingSent();
});
