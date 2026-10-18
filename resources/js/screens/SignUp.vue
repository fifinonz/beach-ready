<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { signedIn } from '../session.js';
import BrandMark from '../components/BrandMark.vue';

const router = useRouter();

const form = reactive({
    first_name: '',
    email: '',
    password: '',
    email_reminders: false,
    // The honeypot (decision 0007). People never see it, so it stays empty.
    favourite_colour: '',
});
const errors = ref({});
const formError = ref(null);
const busy = ref(false);

async function createAccount() {
    busy.value = true;
    errors.value = {};
    formError.value = null;

    try {
        const { data } = await api('POST', '/register', {
            ...form,
            // The browser knows the person's time zone, so we don't ask.
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        });
        signedIn(data);
        await router.push('/protocol');
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            errors.value = error.errors;
        } else if (error instanceof ApiError && error.status === 429) {
            formError.value = 'Too many sign-ups from here just now. Please try again in a few minutes.';
        } else if (error instanceof OfflineError) {
            formError.value = "You're offline. Connect to the internet to create your account.";
        } else {
            formError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        busy.value = false;
    }
}

const errorFor = (field) => errors.value[field]?.[0] ?? null;
</script>

<template>
    <main class="sign-up">
        <BrandMark />

        <div class="intro">
            <h1>Fast at your own pace.</h1>
            <p>A simple timer for intermittent fasting, with gentle reminders when you want them.</p>
        </div>

        <form novalidate @submit.prevent="createAccount">
            <p v-if="formError" class="form-alert" role="alert">{{ formError }}</p>

            <div class="field">
                <label for="first-name">First name</label>
                <input
                    id="first-name"
                    v-model="form.first_name"
                    type="text"
                    autocomplete="given-name"
                    required
                    :aria-invalid="errorFor('first_name') ? 'true' : null"
                    :aria-describedby="errorFor('first_name') ? 'first-name-error' : null"
                />
                <p v-if="errorFor('first_name')" id="first-name-error" class="field-error">{{ errorFor('first_name') }}</p>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    placeholder="you@example.com"
                    required
                    :aria-invalid="errorFor('email') ? 'true' : null"
                    :aria-describedby="errorFor('email') ? 'email-error' : null"
                />
                <p v-if="errorFor('email')" id="email-error" class="field-error">{{ errorFor('email') }}</p>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    placeholder="At least 10 characters"
                    minlength="10"
                    required
                    :aria-invalid="errorFor('password') ? 'true' : null"
                    :aria-describedby="errorFor('password') ? 'password-error' : null"
                />
                <p v-if="errorFor('password')" id="password-error" class="field-error">{{ errorFor('password') }}</p>
            </div>

            <!-- Hidden from sight, screen readers and the tab order (decision 0007). -->
            <div class="honeypot" aria-hidden="true">
                <label for="favourite-colour">Leave this empty</label>
                <input id="favourite-colour" v-model="form.favourite_colour" type="text" name="favourite_colour" tabindex="-1" autocomplete="off" />
            </div>

            <label class="reminders-opt-in">
                <input v-model="form.email_reminders" type="checkbox" aria-describedby="reminders-opt-in-hint" />
                <span>
                    <strong>Email me fasting reminders</strong>
                    <span id="reminders-opt-in-hint">Optional. We'll send one email to confirm before any reminders go out.</span>
                </span>
            </label>

            <button class="button button--block" type="submit" :disabled="busy">
                {{ busy ? 'Creating your account…' : 'Create account' }}
            </button>
        </form>

        <p class="log-in">Already have an account? <RouterLink to="/login">Log in</RouterLink></p>

        <p class="disclaimer">
            Beach Ready is a timer, not medical advice. If you're pregnant, diabetic or have a history of disordered eating, talk to a doctor before fasting.
        </p>
    </main>
</template>

<style scoped>
.sign-up {
    display: grid;
    gap: 32px;
    max-width: 30rem;
    margin: 0 auto;
    padding: 28px var(--gutter) 32px;
}

.intro {
    display: grid;
    gap: 14px;
}

h1 {
    font-size: 2.25rem;
    letter-spacing: -0.02em;
}

.intro p {
    color: var(--muted);
    font-size: 1.0625rem;
}

form {
    display: grid;
    gap: 22px;
}

.reminders-opt-in {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 14px;
    border-radius: var(--radius);
    background: var(--shallows);
    cursor: pointer;
}

.reminders-opt-in input {
    flex: none;
    width: 22px;
    height: 22px;
    margin: 2px 0 0;
    accent-color: var(--lagoon);
}

.reminders-opt-in strong {
    display: block;
    font-size: 1.0625rem;
}

.reminders-opt-in span span {
    color: var(--muted);
    font-size: 0.9375rem;
}

form .button {
    margin-top: 28px;
}

.log-in {
    margin-top: -12px;
    text-align: center;
    font-size: 1.0625rem;
}

.disclaimer {
    color: var(--muted);
    font-size: 0.8125rem;
    text-align: center;
}

/* Off-screen rather than display: none, which some bots skip. */
.honeypot {
    position: absolute;
    left: -10000px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}
</style>
