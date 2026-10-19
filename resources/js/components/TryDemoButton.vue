<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ApiError, OfflineError } from '../api.js';
import { tryTheDemo } from '../demo.js';

// On the sign-up and log-in screens, for anyone who wants to look around first.
const router = useRouter();
const busy = ref(false);
const errorMessage = ref(null);

async function start() {
    busy.value = true;
    errorMessage.value = null;

    try {
        await tryTheDemo(router);
    } catch (error) {
        errorMessage.value = error instanceof ApiError && error.status === 429
            ? 'Too many demos from here just now. Please try again later.'
            : error instanceof OfflineError ? "You're offline. Connect to the internet to try the demo." : 'Something went wrong. Please try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="demo">
        <button type="button" class="button button--block button--outline" :disabled="busy" @click="start">
            {{ busy ? 'Setting up the demo…' : 'Try the demo' }}
        </button>
        <p class="demo__note">No sign-up needed: a demo account with a few weeks of fasts, deleted after 24 hours.</p>
        <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>
    </div>
</template>

<style scoped>
.demo {
    display: grid;
    gap: 8px;
}

.demo__note {
    color: var(--muted);
    font-size: 0.9375rem;
    text-align: center;
}
</style>
