<script setup>
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { signedIn } from '../session.js';
import AuthLayout from '../components/AuthLayout.vue';

// Opened from the link in the reset email: /reset-password/{token}?email=…
const route = useRoute();
const router = useRouter();

const password = ref('');
const passwordError = ref(null);
const linkExpired = ref(false);
const errorMessage = ref(null);
const busy = ref(false);

async function save() {
    busy.value = true;
    passwordError.value = null;
    errorMessage.value = null;

    try {
        const { data } = await api('POST', '/reset-password', {
            token: route.params.token,
            email: route.query.email,
            password: password.value,
        });
        signedIn(data);
        await router.push('/');
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            passwordError.value = error.errorFor('password');
            linkExpired.value = error.errorFor('token') !== null || error.errorFor('email') !== null;
        } else if (error instanceof ApiError && error.status === 429) {
            errorMessage.value = 'Too many attempts. Please try again later.';
        } else if (error instanceof OfflineError) {
            errorMessage.value = "You're offline. Connect to the internet to save your new password.";
        } else {
            errorMessage.value = 'Something went wrong. Please try again.';
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthLayout title="Choose a new password">
        <p class="lead">For {{ route.query.email }}. Saving it signs you out on every other device.</p>

        <p v-if="linkExpired" class="form-alert" role="alert">
            This reset link has expired or has already been used.
            <RouterLink to="/forgot-password">Ask for a new one</RouterLink>.
        </p>

        <form novalidate @submit.prevent="save">
            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <!-- Lets password managers save the new password against the right account. -->
            <input type="email" name="email" :value="route.query.email" autocomplete="username" hidden />

            <div class="field">
                <label for="new-password">New password</label>
                <input
                    id="new-password"
                    v-model="password"
                    type="password"
                    autocomplete="new-password"
                    placeholder="At least 10 characters"
                    minlength="10"
                    required
                    :aria-invalid="passwordError ? 'true' : null"
                    :aria-describedby="passwordError ? 'new-password-error' : null"
                />
                <p v-if="passwordError" id="new-password-error" class="field-error">{{ passwordError }}</p>
            </div>

            <button class="button button--block" type="submit" :disabled="busy || linkExpired">
                {{ busy ? 'Saving…' : 'Save new password' }}
            </button>
        </form>
    </AuthLayout>
</template>
