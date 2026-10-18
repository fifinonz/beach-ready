import { describe, expect, it } from 'vitest';
import { buildWeek, fastDetails, recentDays } from './week.js';

const at = (iso) => Date.parse(iso);
const NAIROBI = 'Africa/Nairobi';
let nextId = 1;

/** A finished fast between two UTC instants. */
function fast(startedAt, endedAt, extra = {}) {
    return {
        id: nextId++,
        protocol: '16:8',
        started_at: startedAt,
        ended_at: endedAt,
        ended_early: false,
        feeling: null,
        duration_seconds: (Date.parse(endedAt) - Date.parse(startedAt)) / 1000,
        ...extra,
    };
}

// Saturday 24 October 2026, 12:00 in Nairobi.
const NOW = at('2026-10-24T09:00:00Z');

describe('the weekly chart', () => {
    it('has Monday to Sunday, with today marked', () => {
        const week = buildWeek({ fasts: [], active: null, timeZone: NAIROBI, now: NOW });

        expect(week.map((day) => day.letter).join('')).toBe('MTWTFSS');
        expect(week.map((day) => day.date)).toEqual([
            '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25',
        ]);
        expect(week.filter((day) => day.isToday).map((day) => day.short)).toEqual(['Sat']);
    });

    it('puts each fast on the day it ended, in the person\'s time zone', () => {
        // Ends 01:00 Tuesday in Nairobi, which is still Monday in UTC.
        const week = buildWeek({ fasts: [fast('2026-10-19T06:00:00Z', '2026-10-19T22:00:00Z')], active: null, timeZone: NAIROBI, now: NOW });

        expect(week[0].status).toBe('rest');
        expect(week[1]).toMatchObject({ status: 'finished', seconds: 16 * 3600 });
    });

    it('shows the longest fast when a day has more than one', () => {
        const week = buildWeek({
            fasts: [fast('2026-10-20T00:00:00Z', '2026-10-20T04:00:00Z'), fast('2026-10-19T20:00:00Z', '2026-10-20T13:00:00Z')],
            active: null,
            timeZone: NAIROBI,
            now: NOW,
        });

        expect(week[1].seconds).toBe(17 * 3600);
    });

    it('shows a fast in progress on today, separately from finished ones', () => {
        const week = buildWeek({ fasts: [], active: { started_at: '2026-10-23T21:36:00Z' }, timeZone: NAIROBI, now: NOW });

        expect(week[5]).toMatchObject({ isToday: true, status: 'in-progress', seconds: 11 * 3600 + 24 * 60 });
    });

    it('marks past days without a fast as rest and later days as future', () => {
        const week = buildWeek({ fasts: [], active: null, timeZone: NAIROBI, now: NOW });

        expect(week.map((day) => day.status)).toEqual(['rest', 'rest', 'rest', 'rest', 'rest', 'rest', 'future']);
    });
});

describe('the list of recent days', () => {
    it('lists fasts newest first, with a single rest day marked "streak kept"', () => {
        const fasts = [
            fast('2026-10-23T18:00:00Z', '2026-10-24T05:24:00Z', { ended_early: true, feeling: 'okay' }), // Today
            fast('2026-10-22T14:00:00Z', '2026-10-23T07:06:00Z'), // Friday
            // Thursday: rest
            fast('2026-10-20T15:00:00Z', '2026-10-21T07:00:00Z'), // Wednesday
        ];

        const rows = recentDays({ fasts, timeZone: NAIROBI, now: NOW });

        expect(rows.map((row) => [row.kind, row.name])).toEqual([
            ['fast', 'Today'],
            ['fast', 'Friday'],
            ['rest', 'Thursday'],
            ['fast', 'Wednesday'],
        ]);
        expect(rows[2].streakKept).toBe(true);
    });

    it('merges rest days in a row, which don\'t keep the streak', () => {
        const fasts = [
            fast('2026-10-22T14:00:00Z', '2026-10-23T07:00:00Z'), // Friday
            fast('2026-10-18T14:00:00Z', '2026-10-19T07:00:00Z'), // Monday
        ];

        const rows = recentDays({ fasts, timeZone: NAIROBI, now: NOW });

        expect(rows.map((row) => [row.kind, row.name])).toEqual([
            ['fast', 'Friday'],
            ['rest', 'Tuesday – Thursday'],
            ['fast', 'Monday'],
        ]);
        expect(rows[1]).toMatchObject({ days: 3, streakKept: false });
    });

    it('leaves today out until a fast has finished today', () => {
        const rows = recentDays({ fasts: [fast('2026-10-22T14:00:00Z', '2026-10-23T07:00:00Z')], timeZone: NAIROBI, now: NOW });

        expect(rows.map((row) => row.name)).toEqual(['Friday']);
    });

    it('is empty before the first fast', () => {
        expect(recentDays({ fasts: [], timeZone: NAIROBI, now: NOW })).toEqual([]);
    });

    it('describes a fast without implying failure', () => {
        expect(fastDetails(fast('a', 'b', { ended_early: true, feeling: 'okay' }))).toBe('16:8 · ended early · felt okay');
        expect(fastDetails(fast('a', 'b', { feeling: 'great' }), { withFeeling: false })).toBe('16:8');
    });
});
