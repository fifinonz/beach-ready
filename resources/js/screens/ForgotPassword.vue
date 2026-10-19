<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import AuthLayout from '../components/AuthLayout.vue';

const router = useRouter();

const email = ref('');
const emailError = ref(null);
const errorMessage = ref(null);
const busy = ref(false);

async function sendLink() {
    busy.value = true;
    emailError.value = null;
    errorMessage.value = null;

    try {
        await api('POST', '/forgot-password', { email: email.value });
        await router.push({ path: '/forgot-password/sent', query: { email: email.value } });
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            emailError.value = error.errorFor('email');
        } else if (error instanceof ApiError && error.status === 429) {
            errorMessage.value = 'Too many reset requests. Please try again in an hour.';
        } else if (error instanceof OfflineError) {
            errorMessage.value = "You're offline. Connect to the internet to reset your password.";
        } else {
            errorMessage.value = 'Something went wrong. Please try again.';
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthLayout title="Forgot your password?">
        <p class="lead">Enter the email you signed up with and we'll send you a link to choose a new password.</p>

        <form novalidate @submit.prevent="sendLink">
            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <div class="field">
                <label for="email">Email</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    required
                    :aria-invalid="emailError ? 'true' : null"
                    :aria-describedby="emailError ? 'email-error' : null"
                />
                <p v-if="emailError" id="email-error" class="field-error">{{ emailError }}</p>
            </div>

            <button class="button button--block" type="submit" :disabled="busy">
                {{ busy ? 'Sending…' : 'Send reset link' }}
            </button>
        </form>

        <p class="after"><RouterLink to="/login">Back to log in</RouterLink></p>
    </AuthLayout>
</template>
