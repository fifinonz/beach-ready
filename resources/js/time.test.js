import { describe, expect, it } from 'vitest';
import {
    addDays, dateTimeLocalValue, dayAndTime, dayName, elapsedSeconds, formatClock, formatDuration,
    formatHours, formatToGo, localDate, localTime, maskEmail, progress, weekdayIndex,
} from './time.js';

const at = (iso) => Date.parse(iso);

describe('elapsed time', () => {
    it('is worked out from the start time, so a phone that slept for hours shows the right time on waking', () => {
        const startedAt = '2026-10-18T17:30:00Z';

        expect(elapsedSeconds(startedAt, at('2026-10-18T17:30:00Z'))).toBe(0);
        // Nothing ran in between: the second reading is just later.
        expect(elapsedSeconds(startedAt, at('2026-10-19T04:54:00Z'))).toBe(11 * 3600 + 24 * 60);
    });

    it('never goes below zero, even if the clock is slightly behind the server', () => {
        expect(elapsedSeconds('2026-10-18T17:30:00Z', at('2026-10-18T17:29:58Z'))).toBe(0);
    });

    it('counts real hours across a daylight saving change', () => {
        // London's clocks go back an hour at 01:00 UTC on 25 October 2026.
        expect(elapsedSeconds('2026-10-24T19:00:00Z', at('2026-10-25T11:00:00Z'))).toBe(16 * 3600);
    });

    it('reports progress towards the goal, capped at done', () => {
        const start = '2026-10-18T00:00:00Z';
        const goal = '2026-10-18T16:00:00Z';

        expect(progress(start, goal, at('2026-10-18T04:00:00Z'))).toBe(0.25);
        expect(progress(start, goal, at('2026-10-18T20:00:00Z'))).toBe(1);
    });
});

describe('formatting', () => {
    it('shows the live clock', () => {
        expect(formatClock(11 * 3600 + 24 * 60)).toBe('11:24:00');
        expect(formatClock(5)).toBe('00:00:05');
        expect(formatClock(21 * 3600 + 59)).toBe('21:00:59');
    });

    it('shows durations in hours and minutes', () => {
        expect(formatDuration(11 * 3600 + 24 * 60)).toBe('11h 24m');
        expect(formatDuration(17 * 3600 + 6 * 60)).toBe('17h 06m');
        expect(formatDuration(45 * 60 + 30)).toBe('45m');
    });

    it('rounds hours for the stat tiles', () => {
        expect(formatHours(15 * 3600 + 20 * 60)).toBe('15h');
    });

    it('says how long is left, then that the goal is reached', () => {
        const goal = '2026-10-19T09:30:00Z';

        expect(formatToGo(goal, at('2026-10-19T04:54:00Z'))).toBe('4h 36m to go');
        expect(formatToGo(goal, at('2026-10-19T09:29:30Z'))).toBe('1m to go');
        expect(formatToGo(goal, at('2026-10-19T09:30:00Z'))).toBe('Goal reached');
    });

    it('shows an email address only partly', () => {
        expect(maskEmail('muthoni@example.com')).toBe('mu••••@example.com');
    });
});

describe('the person\'s time zone', () => {
    it('gives the local date, which can differ from the UTC date', () => {
        // 22:00 UTC on Sunday is 01:00 on Monday in Nairobi.
        expect(localDate('2026-10-18T22:00:00Z', 'Africa/Nairobi')).toBe('2026-10-19');
        expect(localDate('2026-10-18T22:00:00Z', 'Europe/London')).toBe('2026-10-18');
    });

    it('gives the local time of day', () => {
        expect(localTime('2026-10-18T17:30:00Z', 'Africa/Nairobi')).toBe('8:30 pm');
        expect(localTime('2026-07-01T11:00:00Z', 'Europe/London')).toBe('12:00 pm');
        expect(localTime('2026-12-01T11:00:00Z', 'Europe/London')).toBe('11:00 am');
    });

    it('names days relative to today', () => {
        const today = '2026-10-24'; // a Saturday

        expect(dayName('2026-10-24', today)).toBe('Today');
        expect(dayName('2026-10-25', today)).toBe('Tomorrow');
        expect(dayName('2026-10-23', today)).toBe('Friday');
        expect(dayName('2026-10-18', today)).toBe('Sunday');
        expect(dayName('2026-10-17', today)).toBe('17 Oct');
    });

    it('shows the start and goal like the Today screen', () => {
        const now = at('2026-10-18T18:00:00Z'); // 21:00 in Nairobi

        expect(dayAndTime('2026-10-18T17:30:00Z', 'Africa/Nairobi', now)).toBe('Today, 8:30 pm');
        expect(dayAndTime('2026-10-19T09:30:00Z', 'Africa/Nairobi', now)).toBe('Tomorrow, 12:30 pm');
        expect(dayAndTime('2026-10-17T17:30:00Z', 'Africa/Nairobi', now)).toBe('Yesterday, 8:30 pm');
    });

    it('fills a date and time input in local time', () => {
        expect(dateTimeLocalValue('2026-10-18T17:30:00Z', 'Africa/Nairobi')).toBe('2026-10-18T20:30');
        expect(dateTimeLocalValue('2026-10-18T23:05:00Z', 'Africa/Nairobi')).toBe('2026-10-19T02:05');
    });
});

describe('calendar arithmetic', () => {
    it('adds days across months and years', () => {
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
        expect(addDays('2027-01-01', -1)).toBe('2026-12-31');
    });

    it('numbers weekdays from Monday', () => {
        expect(weekdayIndex('2026-10-19')).toBe(0); // Monday
        expect(weekdayIndex('2026-10-25')).toBe(6); // Sunday
    });
});
