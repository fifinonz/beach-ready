<?php

use App\Consent\EmailLinks;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-19 10:00:00');
});

/**
 * A request from a given IP address.
 */
function fromIp(string $ip): TestCase
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip]);
}

describe('registration', function () {
    function register(string $ip, int $n): TestResponse
    {
        return fromIp($ip)->postJson('/api/register', [
            'first_name' => 'Muthoni',
            'email' => "person{$n}@example.com",
            'password' => 'a-long-password',
            'timezone' => 'Africa/Nairobi',
        ]);
    }

    it('allows 10 sign-ups an hour from one IP address', function () {
        foreach (range(1, 10) as $n) {
            register('203.0.113.1', $n)->assertCreated();
            auth()->logout();
        }

        register('203.0.113.1', 11)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        expect(User::count())->toBe(10);
    });

    it('counts each IP address separately', function () {
        foreach (range(1, 10) as $n) {
            register('203.0.113.1', $n);
            auth()->logout();
        }

        register('203.0.113.2', 11)->assertCreated();
    });

    it('allows sign-ups again after an hour', function () {
        foreach (range(1, 10) as $n) {
            register('203.0.113.1', $n);
            auth()->logout();
        }
        $this->travel(61)->minutes();

        register('203.0.113.1', 11)->assertCreated();
    });
});

describe('logging in', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['email' => 'muthoni@example.com', 'password' => 'a-long-password']);
    });

    function logIn(string $ip, string $email, string $password = 'wrong-password'): TestResponse
    {
        return fromIp($ip)->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    it('allows 5 attempts a minute for one email address from one IP address', function () {
        foreach (range(1, 5) as $attempt) {
            logIn('203.0.113.1', 'muthoni@example.com')->assertUnprocessable();
        }

        // Even the right password is refused until the minute is up.
        logIn('203.0.113.1', 'muthoni@example.com', 'a-long-password')->assertTooManyRequests();
        $this->assertGuest();
    });

    it('treats the email address the same in any case', function () {
        foreach (range(1, 5) as $attempt) {
            logIn('203.0.113.1', $attempt % 2 ? 'Muthoni@Example.com' : 'muthoni@example.com');
        }

        logIn('203.0.113.1', 'MUTHONI@EXAMPLE.COM')->assertTooManyRequests();
    });

    it('lets the owner log in from another IP address meanwhile', function () {
        foreach (range(1, 5) as $attempt) {
            logIn('203.0.113.1', 'muthoni@example.com');
        }

        logIn('198.51.100.7', 'muthoni@example.com', 'a-long-password')->assertOk();
    });

    it('allows 20 attempts a minute from one IP address across different accounts', function () {
        foreach (range(1, 20) as $n) {
            logIn('203.0.113.1', "person{$n}@example.com")->assertUnprocessable();
        }

        logIn('203.0.113.1', 'person21@example.com')->assertTooManyRequests();
    });

    it('allows attempts again after a minute', function () {
        foreach (range(1, 5) as $attempt) {
            logIn('203.0.113.1', 'muthoni@example.com');
        }
        $this->travel(61)->seconds();

        logIn('203.0.113.1', 'muthoni@example.com', 'a-long-password')->assertOk();
    });
});

describe('asking for another confirmation email', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('allows 5 an hour for one account, on top of one per 60 seconds', function () {
        foreach (range(1, 5) as $n) {
            $this->postJson('/api/settings/email/confirmation')->assertAccepted();
            $this->travel(61)->seconds();
        }

        $this->postJson('/api/settings/email/confirmation')->assertTooManyRequests();

        Mail::assertQueuedCount(5);
    });

    it('counts each account separately', function () {
        foreach (range(1, 5) as $n) {
            $this->postJson('/api/settings/email/confirmation');
            $this->travel(61)->seconds();
        }

        $this->actingAs(User::factory()->create())
            ->postJson('/api/settings/email/confirmation')
            ->assertAccepted();
    });
});

describe('the public preference center', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('allows 30 requests a minute from one IP address', function () {
        $url = EmailLinks::preferences($this->user);

        foreach (range(1, 30) as $n) {
            fromIp('203.0.113.1')->get($url)->assertOk();
        }

        fromIp('203.0.113.1')->get($url)
            ->assertTooManyRequests()
            ->assertSee('Please wait a moment');
        fromIp('203.0.113.1')->post($url, ['product_news' => '1'])->assertTooManyRequests();
        expect($this->user->fresh()->email_product_news)->toBeFalse();
    });

    it('counts requests with made-up signatures too', function () {
        foreach (range(1, 30) as $n) {
            fromIp('203.0.113.1')->get("/email/preferences/{$this->user->id}?signature=guess{$n}")->assertForbidden();
        }

        fromIp('203.0.113.1')->get(EmailLinks::preferences($this->user))->assertTooManyRequests();
    });

    it('counts each IP address separately', function () {
        $url = EmailLinks::preferences($this->user);

        foreach (range(1, 30) as $n) {
            fromIp('203.0.113.1')->get($url);
        }

        fromIp('198.51.100.7')->get($url)->assertOk();
    });

    it('never limits one-click unsubscribe, which mail providers send from shared servers', function () {
        $url = EmailLinks::unsubscribe($this->user);

        foreach (range(1, 40) as $n) {
            fromIp('203.0.113.1')->post($url)->assertOk();
        }
    });
});
