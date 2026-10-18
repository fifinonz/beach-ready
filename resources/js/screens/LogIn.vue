<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { signedIn } from '../session.js';
import BrandMark from '../components/BrandMark.vue';

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
    <main class="log-in">
        <BrandMark />

        <h1>Welcome back</h1>

        <form novalidate @submit.prevent="logIn">
            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" required />
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required />
            </div>

            <button class="button button--block" type="submit" :disabled="busy">
                {{ busy ? 'Logging in…' : 'Log in' }}
            </button>
        </form>

        <p class="sign-up">New to Beach Ready? <RouterLink to="/signup">Create an account</RouterLink></p>
    </main>
</template>

<style scoped>
.log-in {
    display: grid;
    gap: 32px;
    max-width: 30rem;
    margin: 0 auto;
    padding: 28px var(--gutter) 32px;
}

h1 {
    font-size: 2.25rem;
    letter-spacing: -0.02em;
}

form {
    display: grid;
    gap: 22px;
}

form .button {
    margin-top: 12px;
}

.sign-up {
    text-align: center;
    font-size: 1.0625rem;
}
</style>
