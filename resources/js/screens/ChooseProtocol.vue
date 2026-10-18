<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { session, signedIn } from '../session.js';
import AppIcon from '../components/AppIcon.vue';

/*
 * Step 2 of signing up, and also where "change" leads from Today and
 * Profile. When signing up, the button starts the first fast. Otherwise it
 * saves the choice, and moves the goal of a fast in progress.
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

const current = session.user?.fasting_hours ?? 16;
const isPreset = presets.some((preset) => preset.hours === current);
const choice = ref(isPreset ? String(current) : 'custom');
const customHours = ref(isPreset ? 15 : current);

const onboarding = computed(() => route.query.change === undefined);
const hours = computed(() => (choice.value === 'custom' ? customHours.value : Number(choice.value)));

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

        if (onboarding.value) {
            await api('POST', '/fasts', { fasting_hours: hours.value }).catch((error) => {
                // A fast already running (from another device) is fine.
                if (!(error instanceof ApiError && error.status === 409)) throw error;
            });
        } else if (route.query.change === 'active') {
            await api('PATCH', '/fasts/active', { fasting_hours: hours.value });
        }

        await router.push('/');
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
                {{ onboarding ? 'Start my first fast' : 'Save' }}
            </button>
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
</style>
