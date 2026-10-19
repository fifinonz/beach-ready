<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, OfflineError } from '../api.js';
import { session, signedIn } from '../session.js';
import AppIcon from '../components/AppIcon.vue';

/*
 * Step 2 of signing up, and where "change" leads from Today and Profile.
 * It saves the protocol new fasts use; it never starts a fast by itself.
 *
 * - Signing up (no `change`): "Continue" saves the choice, "Skip for now"
 *   keeps 16:8. Both go to Today, ready for a first fast.
 * - `change=next`: saves the protocol for the next fast.
 * - `change=active`: also moves the goal of the fast in progress.
 *
 * `from` is where to go back to afterwards, such as /profile.
 */
const route = useRoute();
const router = useRouter();

const MIN_HOURS = 12;
const MAX_HOURS = 20;

const presets = [
    { hours: 14, name: '14:10', badge: 'Gentle start' },
    { hours: 16, name: '16:8', badge: 'Most popular' },
    { hours: 18, name: '18:6', badge: null },
];

const choice = ref('16');
const customHours = ref(15);

/** Shows the given hours as chosen: a preset card, or custom. */
function select(current) {
    const isPreset = presets.some((preset) => preset.hours === current);
    choice.value = isPreset ? String(current) : 'custom';
    customHours.value = isPreset ? 15 : current;
}

select(session.user?.fasting_hours ?? 16);

const onboarding = computed(() => route.query.change === undefined);
const changingActive = computed(() => route.query.change === 'active');
const hours = computed(() => (choice.value === 'custom' ? customHours.value : Number(choice.value)));

// Only paths inside the app, never another site.
const returnTo = computed(() => (typeof route.query.from === 'string' && /^\/(?!\/)/.test(route.query.from) ? route.query.from : '/'));

// Changing the fast in progress starts from that fast's protocol, which
// may differ from the saved preference.
onMounted(async () => {
    if (changingActive.value) {
        const active = await api('GET', '/fasts/active').catch(() => null);

        if (active?.data) {
            select(active.data.fasting_hours);
        }
    }
});

const busy = ref(false);
const errorMessage = ref(null);

function stepCustom(by) {
    customHours.value = Math.min(MAX_HOURS, Math.max(MIN_HOURS, customHours.value + by));
}

async function save() {
    busy.value = true;
    errorMessage.value = null;

    try {
        signedIn((await api('PATCH', '/user', { fasting_hours: hours.value })).data);

        if (changingActive.value) {
            await api('PATCH', '/fasts/active', { fasting_hours: hours.value });
        }

        await router.push(returnTo.value);
    } catch (error) {
        errorMessage.value = error instanceof OfflineError
            ? "You're offline. Connect to the internet to save your choice."
            : 'Something went wrong. Please try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="protocol">
        <div class="top">
            <button type="button" class="back" aria-label="Back" @click="router.back()">
                <AppIcon name="back" />
            </button>
            <p v-if="onboarding" class="step">Step 2 of 2</p>
        </div>

        <div class="intro">
            <h1 id="protocol-question">How do you want to fast?</h1>
            <p>Fasting hours, then eating hours. You can change this any time.</p>
        </div>

        <form @submit.prevent="save">
            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <fieldset aria-labelledby="protocol-question">
                <label v-for="preset in presets" :key="preset.hours" class="option">
                    <input v-model="choice" type="radio" name="protocol" :value="String(preset.hours)" />
                    <span class="option__text">
                        <strong>{{ preset.name }}</strong>
                        <span>{{ preset.hours }} hours fasting, {{ 24 - preset.hours }} hours eating</span>
                    </span>
                    <span v-if="preset.badge" class="badge">{{ preset.badge }}</span>
                </label>

                <label class="option">
                    <input v-model="choice" type="radio" name="protocol" value="custom" />
                    <span class="option__text">
                        <strong>Custom</strong>
                        <span>Set your own hours, up to {{ MAX_HOURS }}</span>
                    </span>
                </label>
            </fieldset>

            <div v-if="choice === 'custom'" class="custom">
                <span id="custom-label">Fasting hours</span>
                <div class="stepper" role="group" aria-labelledby="custom-label">
                    <button type="button" aria-label="Fewer hours" :disabled="customHours <= MIN_HOURS" @click="stepCustom(-1)">−</button>
                    <output aria-live="polite">{{ customHours }}:{{ 24 - customHours }}</output>
                    <button type="button" aria-label="More hours" :disabled="customHours >= MAX_HOURS" @click="stepCustom(1)">+</button>
                </div>
            </div>

            <p class="note">
                <AppIcon name="info" />
                <span>Custom fasts are capped at 20 hours. For anything longer, please talk to a doctor first.</span>
            </p>

            <button class="button button--block" type="submit" :disabled="busy">
                {{ onboarding ? 'Continue' : changingActive ? 'Save and update this fast' : 'Save' }}
            </button>
            <RouterLink v-if="onboarding" to="/" class="skip">Skip for now</RouterLink>
        </form>
    </main>
</template>

<style scoped>
.protocol {
    display: grid;
    gap: 24px;
    max-width: 30rem;
    min-height: 100dvh;
    margin: 0 auto;
    padding: 20px var(--gutter) 32px;
}

.top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.back {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 50%;
    background: var(--shallows);
    color: var(--deep);
    cursor: pointer;
}

.step {
    color: var(--muted);
    font-weight: 600;
}

.intro {
    display: grid;
    gap: 12px;
}

h1 {
    font-size: 2rem;
    letter-spacing: -0.02em;
}

.intro p {
    color: var(--muted);
    font-size: 1.0625rem;
}

form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

fieldset {
    display: grid;
    gap: 12px;
    margin: 0;
    padding: 0;
    border: 0;
}

.option {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 76px;
    padding: 16px 16px;
    border: 1.5px solid var(--line);
    border-radius: var(--radius);
    background: #fff;
    cursor: pointer;
}

/* The radio itself is hidden; the whole card shows the choice. */
.option input {
    position: absolute;
    opacity: 0;
}

.option:has(input:checked) {
    border: 2px solid var(--lagoon);
    box-shadow: 0 0 0 4px var(--shallows);
}

.option:has(input:focus-visible) {
    outline: 3px solid var(--deep);
    outline-offset: 3px;
}

.option__text {
    display: grid;
    flex: 1;
    gap: 2px;
}

.option__text strong {
    font-size: 1.375rem;
}

.option__text span {
    color: var(--muted);
    font-size: 0.9375rem;
}

.badge {
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    background: var(--sun);
    color: var(--deep);
    font-size: 0.875rem;
    font-weight: 700;
    white-space: nowrap;
}

.custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    border-radius: var(--radius);
    background: var(--shallows);
    font-weight: 600;
}

.stepper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.stepper button {
    width: 44px;
    height: 44px;
    border: 1.5px solid var(--lagoon);
    border-radius: 50%;
    background: #fff;
    color: var(--lagoon);
    font: 700 1.25rem var(--font);
    cursor: pointer;
}

.stepper button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.stepper output {
    min-width: 3.5rem;
    font-size: 1.25rem;
    font-weight: 700;
    text-align: center;
}

.note {
    display: flex;
    gap: 10px;
    color: var(--muted);
    font-size: 0.9375rem;
}

.note .icon {
    flex: none;
    width: 20px;
    height: 20px;
}

form .button {
    margin-top: 12px;
}

.skip {
    align-self: center;
    padding: 10px 16px;
    font-size: 1.0625rem;
}
</style>
