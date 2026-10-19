<?php

namespace App\Demo;

use App\Enums\Feeling;
use App\Models\Fast;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Try the demo": a fresh account for each visitor, with three weeks of
 * fasting history and a fast in progress, so every screen has something to
 * show. Visitors never share an account, so nobody sees anyone else's
 * changes, and each one is deleted after 24 hours (demo:prune). See
 * decision 0011.
 */
class DemoAccount
{
    /**
     * A reserved top-level domain (RFC 2606) that can never receive mail.
     * Nothing is ever sent to it; see AppServiceProvider.
     */
    public const EMAIL_DOMAIN = 'demo.invalid';

    public const LIFETIME_HOURS = 24;

    public const DAYS_OF_HISTORY = 21;

    /**
     * Days back from today with no fast. Two in a row 16 and 17 days ago end
     * an earlier streak; the single rest days 9 and 4 days ago keep the
     * current one going (decision 0003).
     */
    private const REST_DAYS = [17, 16, 9, 4];

    /** How long each fast lasted, in minutes, used in turn. */
    private const LENGTHS = [972, 940, 965, 1026, 870, 960, 1008, 1082, 910, 980];

    private const FEELINGS = ['great', 'okay', null, 'great', 'hungry', 'okay', null];

    public static function isDemoEmail(string $email): bool
    {
        return str_ends_with(Str::lower($email), '@'.self::EMAIL_DOMAIN);
    }

    public static function create(string $timezone, CarbonImmutable $now): User
    {
        return DB::transaction(function () use ($timezone, $now) {
            $user = User::create([
                'first_name' => 'Alex',
                'email' => 'demo-'.Str::lower(Str::random(16)).'@'.self::EMAIL_DOMAIN,
                // Nobody knows it: the demo is entered by its button, not a password.
                'password' => Str::random(40),
                'timezone' => $timezone,
            ]);
            $user->forceFill([
                'is_demo' => true,
                'fasting_hours' => 16,
                'why' => 'More energy for long beach days.',
            ])->save();

            $lastEnded = self::history($user, $now);
            self::fastInProgress($user, $now, $lastEnded);

            return $user;
        });
    }

    /**
     * One finished 16:8 fast for each day, starting around 8 pm the evening
     * before and ending the next day, apart from the rest days. Returns when
     * the last one ended.
     */
    private static function history(User $user, CarbonImmutable $now): CarbonImmutable
    {
        $today = $now->setTimezone($user->timezone)->startOfDay();
        $lastEnded = $now->subDays(self::DAYS_OF_HISTORY + 1);

        foreach (range(self::DAYS_OF_HISTORY, 1) as $i => $daysAgo) {
            if (in_array($daysAgo, self::REST_DAYS, true)) {
                continue;
            }

            $startedAt = $today->subDays($daysAgo + 1)->setTime(20, ($i * 7) % 40)->utc();
            $endedAt = $startedAt->addMinutes(self::LENGTHS[$i % count(self::LENGTHS)]);
            $feeling = self::FEELINGS[$i % count(self::FEELINGS)];

            $fast = new Fast;
            $fast->changeProtocol(16);
            $fast->startAt($startedAt);
            $fast->end($endedAt, $feeling ? Feeling::from($feeling) : null);
            $user->fasts()->save($fast);

            $lastEnded = $endedAt;
        }

        return $lastEnded;
    }

    /**
     * A fast about ten hours in, so Today shows the timer straight away. It
     * never starts before the last one ended.
     */
    private static function fastInProgress(User $user, CarbonImmutable $now, CarbonImmutable $lastEnded): void
    {
        $startedAt = $now->subMinutes(10 * 60 + 24);

        if ($startedAt->lt($lastEnded->addHour())) {
            $startedAt = $lastEnded->addHour();
        }

        $fast = new Fast;
        $fast->changeProtocol(16);
        $fast->startAt($startedAt->min($now));
        $user->fasts()->save($fast);
    }
}
