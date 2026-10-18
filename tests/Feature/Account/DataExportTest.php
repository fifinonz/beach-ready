<?php

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-19 10:00:00');
    $this->user = User::factory()->receivingReminders()->create([
        'first_name' => 'Muthoni',
        'email' => 'muthoni@example.com',
        'timezone' => 'Africa/Nairobi',
        'remind_eating_window_closing' => true,
    ]);
    $this->actingAs($this->user);
});

it('downloads everything stored about the user as a JSON file', function () {
    $fast = Fast::factory()->for($this->user)->between('2026-10-18 02:00', '2026-10-18 18:00')->create(['feeling' => 'great']);
    Reminder::factory()->for($fast)->create([
        'type' => ReminderType::GoalReached,
        'send_at' => '2026-10-18 18:00:00',
        'status' => ReminderStatus::Sent,
        'sent_at' => '2026-10-18 18:00:30',
    ]);

    $this->get('/api/account/export')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="beach-ready-data-2026-10-19.json"')
        ->assertJson([
            'exported_at' => '2026-10-19T10:00:00Z',
            'profile' => [
                'first_name' => 'Muthoni',
                'email' => 'muthoni@example.com',
                'timezone' => 'Africa/Nairobi',
            ],
            'email_settings' => [
                'fasting_reminders' => true,
                'weekly_summary' => false,
                'product_news' => false,
                'email_confirmed_at' => '2026-10-19T10:00:00Z',
            ],
            'reminder_settings' => [
                'before_goal' => true,
                'goal_reached' => true,
                'eating_window_closing' => true,
            ],
            'fasts' => [[
                'protocol' => '16:8',
                'started_at' => '2026-10-18T02:00:00Z',
                'ended_at' => '2026-10-18T18:00:00Z',
                'ended_early' => false,
                'feeling' => 'great',
                'reminders' => [[
                    'type' => 'goal_reached',
                    'send_at' => '2026-10-18T18:00:00Z',
                    'status' => 'sent',
                    'sent_at' => '2026-10-18T18:00:30Z',
                ]],
            ]],
        ]);
});

it('leaves out the password and session token', function () {
    $json = $this->get('/api/account/export')->getContent();

    expect($json)->not->toContain('password')
        ->and($json)->not->toContain('remember_token')
        ->and($json)->not->toContain($this->user->password);
});

it('includes the fast in progress', function () {
    Fast::factory()->for($this->user)->create();

    $this->get('/api/account/export')->assertJsonPath('fasts.0.ended_at', null);
});

it('never includes anyone else\'s data', function () {
    $someoneElse = User::factory()->create(['email' => 'someone@example.com']);
    Fast::factory()->for($someoneElse)->between('2026-10-18 02:00', '2026-10-18 18:00')->create();

    $response = $this->get('/api/account/export')->assertJsonCount(0, 'fasts');

    expect($response->getContent())->not->toContain('someone@example.com');
});

it('refuses guests', function () {
    auth()->logout();

    $this->getJson('/api/account/export')->assertUnauthorized();
});
