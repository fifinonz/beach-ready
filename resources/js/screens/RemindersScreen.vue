<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api, ApiError, OfflineError } from '../api.js';
import { session } from '../session.js';
import { maskEmail } from '../time.js';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';

const types = [
    { key: 'before_goal', label: '30 minutes to go', description: 'Before your fasting goal' },
    { key: 'goal_reached', label: 'Goal reached', description: 'When your fast hits its target' },
    { key: 'eating_window_closing', label: 'Eating window closing', description: '1 hour before your next fast' },
];

const settings = ref(null);
const email = ref(null);
const errorMessage = ref(null);

// The resend countdown is worked out from when a resend becomes allowed,
// re-read every second.
const resendAt = ref(0);
const now = ref(Date.now());
let timer = null;

onMounted(async () => {
    timer = setInterval(() => (now.value = Date.now()), 1000);

    try {
        const [reminders, emailSettings] = await Promise.all([api('GET', '/settings/reminders'), api('GET', '/settings/email')]);
        settings.value = reminders.data;
        applyEmail(emailSettings.data);
    } catch (error) {
        errorMessage.value = messageFor(error);
    }
});

onBeforeUnmount(() => clearInterval(timer));

function applyEmail(data) {
    email.value = data;
    resendAt.value = Date.now() + (data.resend_available_in_seconds ?? 0) * 1000;
}

function messageFor(error) {
    return error instanceof OfflineError
        ? "You're offline. Changes to reminders need a connection."
        : 'Something went wrong. Please try again.';
}

/** Switches a reminder type on or off, and puts it back if saving fails. */
async function setType(key, on) {
    const before = settings.value[key];
    settings.value[key] = on;
    errorMessage.value = null;

    try {
        settings.value = (await api('PATCH', '/settings/reminders', { [key]: on })).data;
    } catch (error) {
        settings.value[key] = before;
        errorMessage.value = messageFor(error);
    }
}

/** The Email card: turns reminder emails on (asking for confirmation) or off. */
async function toggleEmail() {
    errorMessage.value = null;

    try {
        applyEmail((await api('PATCH', '/settings/email', { fasting_reminders: !email.value.fasting_reminders })).data);
    } catch (error) {
        errorMessage.value = messageFor(error);
    }
}

const waiting = computed(() => email.value?.fasting_reminders && !email.value.email_confirmed);
const secondsToResend = computed(() => Math.max(0, Math.ceil((resendAt.value - now.value) / 1000)));
const resendStatus = ref(null);

async function resend() {
    resendStatus.value = null;

    try {
        await api('POST', '/settings/email/confirmation');
        resendAt.value = Date.now() + 60 * 1000;
        resendStatus.value = 'Sent. Check your inbox.';
    } catch (error) {
        if (error instanceof ApiError && error.status === 429) {
            resendAt.value = Date.now() + 60 * 1000;
            resendStatus.value = 'Please wait a little before asking again.';
        } else {
            resendStatus.value = messageFor(error);
        }
    }
}

const emailStatus = computed(() => {
    if (!email.value?.fasting_reminders) return 'Off';
    return email.value.email_confirmed ? 'On' : 'Waiting for confirmation';
});

const timeZone = computed(() => session.user.timezone);
const zoneName = computed(() =>
    new Intl.DateTimeFormat('en-KE', { timeZone: timeZone.value, timeZoneName: 'short' })
        .formatToParts(new Date())
        .find((part) => part.type === 'timeZoneName')?.value,
);
const countdown = computed(() => `${Math.floor(secondsToResend.value / 60)}:${String(secondsToResend.value % 60).padStart(2, '0')}`);
</script>

<template>
    <AppLayout>
        <PageHeader title="Reminders" />

        <div class="reminders">
            <section v-if="waiting" class="confirm" aria-labelledby="confirm-title">
                <h2 id="confirm-title"><AppIcon name="mail" /> Confirm your email to turn on email reminders</h2>
                <p>We sent a link to {{ maskEmail(session.user.email) }}. Nothing is emailed until you click it.</p>
                <p class="confirm__actions">
                    <button type="button" class="link" :disabled="secondsToResend > 0" @click="resend">
                        {{ secondsToResend > 0 ? `Resend in ${countdown}` : 'Resend the email' }}
                    </button>
                </p>
                <p class="confirm__status" aria-live="polite">{{ resendStatus }}</p>
            </section>

            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <template v-if="settings && email">
                <section class="card types" aria-label="Reminders">
                    <ToggleSwitch
                        v-for="type in types"
                        :key="type.key"
                        :model-value="settings[type.key]"
                        :label="type.label"
                        :description="type.description"
                        @update:model-value="(on) => setType(type.key, on)"
                    />
                </section>

                <section aria-labelledby="channels-title">
                    <h2 id="channels-title" class="channels__title">Send reminders by</h2>
                    <div class="channels">
                        <button type="button" class="channel" :aria-pressed="email.fasting_reminders ? 'true' : 'false'" @click="toggleEmail">
                            <strong>Email</strong>
                            <span :class="{ 'is-waiting': waiting }">{{ emailStatus }}</span>
                        </button>
                        <button type="button" class="channel" disabled aria-describedby="phone-note">
                            <strong>Phone</strong>
                            <span id="phone-note">Not available yet</span>
                        </button>
                    </div>
                </section>

                <p class="zone">
                    <AppIcon name="globe" />
                    <span>Time zone: {{ timeZone }}<template v-if="zoneName"> ({{ zoneName }})</template>. If you travel, reminders follow your phone's time zone.</span>
                </p>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.reminders {
    display: grid;
    gap: 24px;
    padding: 8px var(--gutter) 24px;
}

.confirm {
    display: grid;
    gap: 8px;
    padding: 18px 20px;
    border-radius: 20px;
    background: #fff1cf;
    color: #3d2a00;
}

.confirm h2 {
    display: flex;
    gap: 12px;
    font-size: 1.25rem;
}

.confirm h2 .icon {
    flex: none;
    margin-top: 2px;
}

.confirm__actions {
    display: flex;
    gap: 24px;
}

.link {
    min-height: 44px;
    padding: 0;
    border: 0;
    background: none;
    color: #6b4700;
    font: 700 1.0625rem var(--font);
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
}

.link:disabled {
    text-decoration: none;
    cursor: default;
}

.confirm__status:empty {
    display: none;
}

.types {
    padding: 0 20px;
}

.channels__title {
    margin-bottom: 12px;
    font-size: 1.0625rem;
}

.channels {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.channel {
    display: grid;
    align-content: start;
    gap: 2px;
    min-height: 88px;
    padding: 14px 16px;
    border: 1.5px solid var(--line);
    border-radius: var(--radius);
    background: #fff;
    color: var(--deep);
    font: 400 1rem var(--font);
    text-align: left;
    cursor: pointer;
}

.channel strong {
    font-size: 1.25rem;
}

.channel span {
    color: var(--muted);
}

.channel[aria-pressed='true'] {
    border: 2.5px solid var(--lagoon);
}

.channel .is-waiting {
    color: #7a4f00;
    font-weight: 700;
}

.channel:disabled {
    cursor: not-allowed;
}

.zone {
    display: flex;
    gap: 12px;
    color: var(--muted);
}

.zone .icon {
    flex: none;
}

@media (min-width: 1024px) {
    .reminders {
        padding: 0;
    }
}
</style>
