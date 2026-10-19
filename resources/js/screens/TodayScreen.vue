<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { session } from '../session.js';
import { network } from '../network.js';
import { dateTimeLocalValue, dayAndTime, formatHours, localTime } from '../time.js';
import { buildWeek, recentDays } from '../week.js';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import EndFastSheet from '../components/EndFastSheet.vue';
import ModalSheet from '../components/ModalSheet.vue';
import PageHeader from '../components/PageHeader.vue';
import RecentDays from '../components/RecentDays.vue';
import TimerRing from '../components/TimerRing.vue';
import WeekChart from '../components/WeekChart.vue';

const router = useRouter();
const ACTIVE_FAST_KEY = 'beach-ready:active-fast';

const loaded = ref(false);
const fast = ref(null);
const stats = ref(null);
const history = ref([]);
const emailSettings = ref(null);
const reminderSettings = ref(null);
const errorMessage = ref(null);

const user = computed(() => session.user);
const timeZone = computed(() => user.value.timezone);
const fastingHours = computed(() => user.value.fasting_hours ?? 16);

// "The timer never counts": `now` is re-read from the clock every second,
// and straight away when the page becomes visible again.
const now = ref(Date.now());
const tick = () => (now.value = Date.now());
let timer = null;

onMounted(async () => {
    timer = setInterval(tick, 1000);
    document.addEventListener('visibilitychange', tick);
    await load();
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', tick);
});

async function load() {
    try {
        const { data } = await api('GET', '/fasts/active');
        rememberFast(data);
    } catch (error) {
        // Offline: show the fast this device last knew about. The timer
        // still runs, because it only needs the start time.
        if (!(error instanceof OfflineError)) throw error;
        fast.value = JSON.parse(localStorage.getItem(ACTIVE_FAST_KEY) ?? 'null');
    } finally {
        loaded.value = true;
    }

    // The rest is extra detail; the screen works without it.
    const [weekStats, page, email, reminders] = await Promise.allSettled([
        api('GET', '/stats/week'),
        api('GET', '/fasts'),
        api('GET', '/settings/email'),
        api('GET', '/settings/reminders'),
    ]);
    stats.value = weekStats.value?.data ?? null;
    history.value = page.value?.data ?? [];
    emailSettings.value = email.value?.data ?? null;
    reminderSettings.value = reminders.value?.data ?? null;
}

function rememberFast(data) {
    fast.value = data;
    localStorage.setItem(ACTIVE_FAST_KEY, JSON.stringify(data));
}

/* Starting */

const starting = ref(false);

async function startFast() {
    starting.value = true;
    errorMessage.value = null;

    try {
        rememberFast((await api('POST', '/fasts', { fasting_hours: fastingHours.value })).data);
    } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
            await load();
        } else {
            errorMessage.value = error instanceof OfflineError
                ? "You're offline. Connect to the internet to start a fast."
                : 'Something went wrong. Please try again.';
        }
    } finally {
        starting.value = false;
    }
}

/* Editing the start time */

const editing = ref(false);
const editValue = ref('');
const editError = ref(null);
const editBusy = ref(false);
const DAY = 24 * 3600 * 1000;

function openEdit() {
    editValue.value = dateTimeLocalValue(fast.value.started_at, timeZone.value);
    editError.value = null;
    editing.value = true;
}

async function saveStartTime() {
    editBusy.value = true;
    editError.value = null;

    try {
        // Sent without an offset: the server reads it in the person's time zone.
        rememberFast((await api('PATCH', '/fasts/active', { started_at: editValue.value })).data);
        editing.value = false;
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            editError.value = error.errorFor('started_at');
        } else {
            editError.value = error instanceof OfflineError
                ? "You're offline. Connect to the internet to change the start time."
                : 'Something went wrong. Please try again.';
        }
    } finally {
        editBusy.value = false;
    }
}

/* Ending */

const ending = ref(false);

const notice = ref(null);

function fastEnded(ended) {
    localStorage.removeItem(ACTIVE_FAST_KEY);

    // Too short to count: it wasn't saved, so there's nothing in History.
    if (ended === null) {
        ending.value = false;
        fast.value = null;
        notice.value = "Fast ended. It was under an hour, so it isn't in your history. Start again whenever you're ready.";
        return;
    }

    router.push({ path: '/history', query: { saved: ended.duration_seconds } });
}

/* The reminder card */

const nextReminder = computed(() => {
    if (!fast.value || !emailSettings.value?.fasting_reminders || !reminderSettings.value) {
        return null;
    }

    const goal = Date.parse(fast.value.target_end_at);
    const next = [
        reminderSettings.value.before_goal && { at: goal - 30 * 60 * 1000, what: '30 minutes before your goal' },
        reminderSettings.value.goal_reached && { at: goal, what: 'when you reach your goal' },
    ].find((reminder) => reminder && reminder.at > now.value);

    if (!next) {
        return null;
    }

    return {
        title: `Reminder at ${localTime(next.at, timeZone.value)}`,
        line: emailSettings.value.email_confirmed
            ? `${next.what[0].toUpperCase()}${next.what.slice(1)}, by email`
            : `${next.what[0].toUpperCase()}${next.what.slice(1)}, once you confirm your email`,
    };
});

/* The desktop "This week" panel */

const week = computed(() => buildWeek({ fasts: history.value, active: fast.value, timeZone: timeZone.value, now: now.value }));
const days = computed(() => recentDays({ fasts: history.value, timeZone: timeZone.value, now: now.value }).slice(0, 3));
</script>

<template>
    <AppLayout wide>
        <PageHeader :eyebrow="`Hi ${user.first_name}`" :title="fast ? `You're fasting` : 'Ready when you are'" />

        <div v-if="loaded" class="today">
            <section class="main" aria-label="Your fast">
                <template v-if="fast">
                    <TimerRing :fast="fast" :now="now" />

                    <div class="times">
                        <div class="time card">
                            <span class="time__label">Started</span>
                            <strong>{{ dayAndTime(fast.started_at, timeZone, now) }}</strong>
                            <button type="button" class="link" :disabled="!network.online" @click="openEdit">Edit start time</button>
                        </div>
                        <div class="time card">
                            <span class="time__label">Goal</span>
                            <strong>{{ dayAndTime(fast.target_end_at, timeZone, now) }}</strong>
                            <RouterLink to="/protocol?change=active">{{ fast.protocol }} · change</RouterLink>
                        </div>
                    </div>

                    <div v-if="nextReminder" class="reminder reminder--phone">
                        <AppIcon name="reminders" />
                        <p><strong>{{ nextReminder.title }}</strong><br />{{ nextReminder.line }}</p>
                    </div>

                    <button type="button" class="button button--block" @click="ending = true">End fast</button>
                </template>

                <template v-else>
                    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
                    <div class="idle card">
                        <p class="idle__label">Your protocol</p>
                        <p class="idle__protocol">{{ fastingHours }}:{{ 24 - fastingHours }}</p>
                        <p>{{ fastingHours }} hours fasting, then {{ 24 - fastingHours }} hours eating.</p>
                        <RouterLink to="/protocol?change=next" class="idle__change">Change protocol</RouterLink>
                    </div>
                    <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>
                    <button type="button" class="button button--block" :disabled="starting" @click="startFast">
                        {{ starting ? 'Starting…' : 'Start fast' }}
                    </button>
                </template>
            </section>

            <aside class="side" aria-label="This week">
                <div v-if="nextReminder" class="reminder">
                    <AppIcon name="reminders" />
                    <p><strong>{{ nextReminder.title }}</strong><br />{{ nextReminder.line }}</p>
                </div>

                <section class="card panel">
                    <div class="panel__head">
                        <h2>This week</h2>
                        <span v-if="fast" class="panel__legend">Light bar: today, in progress</span>
                        <RouterLink to="/history">See history</RouterLink>
                    </div>
                    <dl v-if="stats" class="panel__stats">
                        <div><dt>fasts finished</dt><dd>{{ stats.fasts_this_week }}</dd></div>
                        <div><dt>average</dt><dd>{{ stats.average_fast_seconds ? formatHours(stats.average_fast_seconds) : '–' }}</dd></div>
                        <div><dt>streak</dt><dd>{{ stats.current_streak_days }} {{ stats.current_streak_days === 1 ? 'day' : 'days' }}</dd></div>
                    </dl>
                    <WeekChart :days="week" :goal-hours="fastingHours" />
                </section>

                <section v-if="days.length" class="card panel">
                    <h2 class="visually-hidden">Recent days</h2>
                    <RecentDays :rows="days" />
                </section>
            </aside>
        </div>

        <EndFastSheet v-if="ending && fast" :fast="fast" :now="now" @close="ending = false" @ended="fastEnded" />

        <ModalSheet v-if="editing" title="Edit start time" @close="editing = false">
            <form class="edit" @submit.prevent="saveStartTime">
                <div class="field">
                    <label for="started-at">When did this fast start?</label>
                    <input
                        id="started-at"
                        v-model="editValue"
                        type="datetime-local"
                        autofocus
                        required
                        :min="dateTimeLocalValue(now - DAY, timeZone)"
                        :max="dateTimeLocalValue(now, timeZone)"
                        :aria-invalid="editError ? 'true' : null"
                        :aria-describedby="editError ? 'started-at-error' : 'started-at-hint'"
                    />
                    <p v-if="editError" id="started-at-error" class="field-error">{{ editError }}</p>
                    <p v-else id="started-at-hint" class="hint">Up to 24 hours ago, and not before your last fast ended. Your goal moves with it.</p>
                </div>
                <button class="button button--block" type="submit" :disabled="editBusy">Save start time</button>
                <button class="button button--block button--outline" type="button" @click="editing = false">Cancel</button>
            </form>
        </ModalSheet>
    </AppLayout>
</template>

<style scoped>
.today {
    padding: 8px var(--gutter) 24px;
}

.main {
    display: grid;
    gap: 20px;
}

.times {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.time {
    display: grid;
    align-content: start;
    gap: 2px;
    padding: 12px 14px;
}

.time__label {
    color: var(--muted);
}

.time strong {
    font-size: 1.125rem;
    line-height: 1.25;
}

.link {
    justify-self: start;
    min-height: 32px;
    padding: 0;
    text-align: left;
    border: 0;
    background: none;
    color: var(--lagoon);
    font: 600 1rem var(--font);
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
}

.link:disabled {
    color: var(--muted);
    cursor: not-allowed;
}

.reminder {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border-radius: var(--radius);
    background: var(--shallows);
}

.reminder .icon {
    flex: none;
}

.reminder strong {
    font-size: 1.0625rem;
}

.notice {
    padding: 14px 18px;
    border-radius: var(--radius);
    background: var(--shallows);
}

.idle {
    display: grid;
    gap: 6px;
    padding: 24px;
}

.idle__label {
    color: var(--muted);
    font-weight: 600;
}

.idle__protocol {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1.1;
}

.idle__change {
    justify-self: start;
    padding: 10px 0 0;
    font-size: 1.0625rem;
}

.side {
    display: none;
}

.edit {
    display: grid;
    gap: 14px;
}

.hint {
    color: var(--muted);
    font-size: 0.9375rem;
}

/* Desktop: the timer card beside "This week", as in 10-desktop-today.png. */
@media (min-width: 1024px) {
    .today {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
        align-items: start;
        gap: 32px;
        padding: 0;
    }

    .main {
        gap: 28px;
        padding: 48px 32px 40px;
        border: 1.5px solid var(--line);
        border-radius: 24px;
        background: #fff;
    }

    .reminder--phone {
        display: none;
    }

    .side {
        display: grid;
        gap: 20px;
    }

    .panel {
        padding: 20px 24px;
    }

    .panel__head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 16px;
        white-space: nowrap;
    }

    .panel__head h2 {
        margin-right: auto;
        font-size: 1.375rem;
    }

    .panel__legend {
        color: var(--muted);
        font-size: 0.9375rem;
    }

    .panel__stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        margin: 16px 0 0;
    }

    .panel__stats div {
        display: flex;
        flex-direction: column-reverse;
    }

    .panel__stats dd {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
    }

    .panel__stats dt {
        color: var(--muted);
    }
}
</style>
