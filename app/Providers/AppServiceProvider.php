<?php

namespace App\Providers;

use App\Demo\DemoAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimits();
        $this->neverEmailDemoAccounts();
    }

    /**
     * The last line of defence for demo accounts (decision 0011): any email
     * addressed to the reserved demo domain is stopped at the moment of
     * sending, whatever queued it. Returning false cancels the send.
     */
    private function neverEmailDemoAccounts(): void
    {
        Event::listen(function (MessageSending $event) {
            foreach ($event->message->getTo() as $address) {
                if (DemoAccount::isDemoEmail($address->getAddress())) {
                    return false;
                }
            }
        });
    }

    /**
     * Limits on the endpoints that anyone, or any bot, can reach. The
     * numbers are generous for a person and tight for a script; see
     * decision 0007. A request over a limit gets a 429 with Retry-After.
     */
    private function configureRateLimits(): void
    {
        // Demo accounts from one IP address: ten an hour is plenty for a
        // curious visitor and keeps a script from filling the database.
        RateLimiter::for('demo', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Sign-ups from one IP address. Ten an hour leaves room for a
        // household or an office sharing one connection.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Log-in attempts: five a minute for one email address from one IP
        // address, against guessing one person's password, and twenty a
        // minute from one IP address, against trying many accounts.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Password reset requests: five an hour from one IP address, and
        // five an hour for one email address from anywhere, so nobody can
        // flood an inbox with reset emails.
        RateLimiter::for('forgot-password', fn (Request $request) => [
            Limit::perHour(5)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);

        // Setting a new password with a reset token: ten an hour from one IP
        // address. Tokens can't be guessed, but there's no reason to let a
        // script try.
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Confirmation resends: five an hour for one account. The domain
        // rule of one per 60 seconds still applies underneath.
        RateLimiter::for('confirmation-resend', fn (Request $request) => Limit::perHour(5)->by((string) $request->user()?->id));

        // The public preference center, reached from links in emails.
        RateLimiter::for('preference-center', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Every signed-in API request: 120 a minute for one account. The app
        // makes a few requests per screen, so only a script gets near it.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id));

        // Deleting an account checks the password, so it's limited like
        // log-in attempts: five a minute for one account.
        RateLimiter::for('account-delete', fn (Request $request) => Limit::perMinute(5)->by((string) $request->user()?->id));
    }
}
