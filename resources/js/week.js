/**
 * The weekly chart and the day-by-day list on Today and History. They use
 * the same rules as the server's weekly stats (decisions 0002 and 0003):
 * days and weeks are the person's own, weeks start on Monday, and a fast
 * belongs to the day it ended on.
 */
import { addDays, elapsedSeconds, localDate, weekdayIndex, dayName } from './time.js';

const LETTERS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
const SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * This week, Monday to Sunday, one entry per day:
 *
 * - finished: a fast ended that day; `seconds` is the longest one
 * - in-progress: today, while a fast is running; `seconds` so far. It's a
 *   preview, not a finished fast, so the stats don't count it
 * - rest: a past day, or today, with nothing finished yet
 * - future: later this week
 */
export function buildWeek({ fasts, active, timeZone, now }) {
    const today = localDate(now, timeZone);
    const monday = addDays(today, -weekdayIndex(today));
    const longestByDay = longestFastPerDay(fasts, timeZone);

    return Array.from({ length: 7 }, (_, i) => {
        const date = addDays(monday, i);
        const day = { date, letter: LETTERS[i], short: SHORT[i], isToday: date === today, seconds: 0 };

        if (longestByDay.has(date)) {
            return { ...day, status: 'finished', seconds: longestByDay.get(date) };
        }

        if (date === today && active) {
            return { ...day, status: 'in-progress', seconds: elapsedSeconds(active.started_at, now) };
        }

        return { ...day, status: date > today ? 'future' : 'rest' };
    });
}

/**
 * Rows for a list of recent days, newest first: one per finished fast, and
 * one for each day in between with none. A single rest day is marked
 * "streak kept", because one rest day doesn't break a streak; two in a row
 * do (decision 0003). Today only appears once something has finished.
 */
export function recentDays({ fasts, timeZone, now }) {
    if (fasts.length === 0) {
        return [];
    }

    const today = localDate(now, timeZone);
    const byDay = new Map();

    for (const fast of fasts) {
        const date = localDate(fast.ended_at, timeZone);
        byDay.set(date, [...(byDay.get(date) ?? []), fast]);
    }

    const oldest = [...byDay.keys()].sort()[0];
    const rows = [];

    for (let date = today; date >= oldest; date = addDays(date, -1)) {
        const name = dayName(date, today);

        if (byDay.has(date)) {
            for (const fast of byDay.get(date)) {
                rows.push({ kind: 'fast', key: `fast-${fast.id}`, date, name, fast });
            }
        } else if (date !== today) {
            const previous = rows.at(-1);

            // Several rest days in a row share one row: "Mon – Wed".
            if (previous?.kind === 'rest' && previous.from === addDays(date, 1)) {
                previous.from = date;
                previous.days += 1;
                previous.name = `${name} – ${previous.newestName}`;
                previous.streakKept = false;
            } else {
                const streakKept = byDay.has(addDays(date, 1)) && byDay.has(addDays(date, -1));
                rows.push({ kind: 'rest', key: `rest-${date}`, date, from: date, days: 1, name, newestName: name, streakKept });
            }
        }
    }

    return rows;
}

function longestFastPerDay(fasts, timeZone) {
    const longest = new Map();

    for (const fast of fasts) {
        const date = localDate(fast.ended_at, timeZone);
        longest.set(date, Math.max(longest.get(date) ?? 0, fast.duration_seconds));
    }

    return longest;
}

/** "16:8 · ended early · felt okay", the details under a fast in a list. */
export function fastDetails(fast, { withFeeling = true } = {}) {
    return [fast.protocol, fast.ended_early ? 'ended early' : null, withFeeling && fast.feeling ? `felt ${fast.feeling}` : null]
        .filter(Boolean)
        .join(' · ');
}
