<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { signedIn } from '../session.js';
import AuthLayout from '../components/AuthLayout.vue';
import TryDemoButton from '../components/TryDemoButton.vue';

// There's no design for this screen; it follows the sign-up screen's style.
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errorMessage = ref(null);
const busy = ref(false);

async function logIn() {
    busy.value = true;
    errorMessage.value = null;

    try {
        const { data } = await api('POST', '/login', form);
        signedIn(data);
        await router.push('/');
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            errorMessage.value = error.errorFor('email') ?? error.errorFor('password');
        } else if (error instanceof ApiError && error.status === 429) {
            errorMessage.value = 'Too many attempts. Please wait a minute and try again.';
        } else if (error instanceof OfflineError) {
            errorMessage.value = "You're offline. Connect to the internet to log in.";
        } else {
            errorMessage.value = 'Something went wrong. Please try again.';
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthLayout title="Welcome back">
        <form novalidate @submit.prevent="logIn">
            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" required />
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required />
                <RouterLink to="/forgot-password" class="forgot">Forgot password?</RouterLink>
            </div>

            <button class="button button--block" type="submit" :disabled="busy">
                {{ busy ? 'Logging in…' : 'Log in' }}
            </button>
        </form>

        <p class="after">New to Beach Ready? <RouterLink to="/signup">Create an account</RouterLink></p>

        <TryDemoButton />
    </AuthLayout>
</template>

<style scoped>
.forgot {
    justify-self: end;
    padding: 8px 0;
}
</style>
