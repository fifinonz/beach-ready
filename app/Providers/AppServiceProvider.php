<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }

    /**
     * Limits on the endpoints that anyone, or any bot, can reach. The
     * numbers are generous for a person and tight for a script; see
     * decision 0007. A request over a limit gets a 429 with Retry-After.
     */
    private function configureRateLimits(): void
    {
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
