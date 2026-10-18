/**
 * Time helpers for the screens. The API sends UTC instants (decision 0002);
 * these turn them into what people read, in their own time zone. Elapsed
 * time is always worked out from the start time and the current clock,
 * never by counting ticks, so a sleeping phone or background tab can't
 * drift.
 */

const MINUTE = 60;
const HOUR = 60 * MINUTE;

/** Whole seconds since `startedAt`, never negative. */
export function elapsedSeconds(startedAt, now) {
    return Math.max(0, Math.floor((now - Date.parse(startedAt)) / 1000));
}

/** How far through the fast, from 0 to 1. */
export function progress(startedAt, targetEndAt, now) {
    const total = Date.parse(targetEndAt) - Date.parse(startedAt);

    return total <= 0 ? 1 : Math.min(1, Math.max(0, (now - Date.parse(startedAt)) / total));
}

const pad = (n) => String(n).padStart(2, '0');

/** "11:24:00", for the live timer. */
export function formatClock(seconds) {
    const hours = Math.floor(seconds / HOUR);
    const minutes = Math.floor((seconds % HOUR) / MINUTE);

    return `${pad(hours)}:${pad(minutes)}:${pad(seconds % MINUTE)}`;
}

/** "11h 24m", "17h 06m" or "45m". */
export function formatDuration(seconds) {
    const hours = Math.floor(seconds / HOUR);
    const minutes = Math.floor((seconds % HOUR) / MINUTE);

    return hours === 0 ? `${minutes}m` : `${hours}h ${pad(minutes)}m`;
}

/** "15h", for the stat tiles. */
export function formatHours(seconds) {
    return `${Math.round(seconds / HOUR)}h`;
}

/** "4h 36m to go", or "Goal reached" once the goal has passed. */
export function formatToGo(targetEndAt, now) {
    const remaining = Math.ceil((Date.parse(targetEndAt) - now) / 1000);

    if (remaining <= 0) {
        return 'Goal reached';
    }

    return `${formatDuration(Math.ceil(remaining / MINUTE) * MINUTE)} to go`;
}

/** The calendar date, "2026-10-18", of an instant in a time zone. */
export function localDate(instant, timeZone) {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' })
        .format(new Date(instant));
}

/** "8:30 pm", in the person's time zone. */
export function localTime(instant, timeZone) {
    return new Intl.DateTimeFormat('en-US', { timeZone, hour: 'numeric', minute: '2-digit' })
        .format(new Date(instant))
        .toLowerCase();
}

/** Calendar arithmetic on "YYYY-MM-DD" strings, free of time zones. */
export function addDays(date, days) {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + days);

    return day.toISOString().slice(0, 10);
}

/** 0 for Monday through 6 for Sunday. */
export function weekdayIndex(date) {
    return (new Date(`${date}T00:00:00Z`).getUTCDay() + 6) % 7;
}

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

/** "Today", "Tomorrow", a weekday within the last week, or "12 Oct". */
export function dayName(date, today) {
    if (date === today) {
        return 'Today';
    }

    if (date === addDays(today, 1)) {
        return 'Tomorrow';
    }

    if (date < today && date > addDays(today, -7)) {
        return WEEKDAYS[weekdayIndex(date)];
    }

    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' })
        .format(new Date(`${date}T00:00:00Z`));
}

/**
 * "Today, 8:30 pm", "Tomorrow, 12:30 pm" or "Yesterday, 8:30 pm", for a
 * fast's start and goal. (Lists of days use weekday names instead, as the
 * history designs do.)
 */
export function dayAndTime(instant, timeZone, now) {
    const date = localDate(instant, timeZone);
    const today = localDate(now, timeZone);
    const name = date === addDays(today, -1) ? 'Yesterday' : dayName(date, today);

    return `${name}, ${localTime(instant, timeZone)}`;
}

/** The value for an <input type="datetime-local">, in the person's time zone. */
export function dateTimeLocalValue(instant, timeZone) {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(instant))
            .map(({ type, value }) => [type, value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

/** "mu••••@example.com", so an address isn't shown in full on screen. */
export function maskEmail(email) {
    const [name, domain] = email.split('@');

    return `${name.slice(0, 2)}••••@${domain}`;
}

/** The browser's own IANA time zone, such as "Africa/Nairobi". */
export function browserTimeZone() {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}
