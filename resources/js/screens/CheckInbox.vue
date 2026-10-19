<script setup>
import { RouterLink, useRoute } from 'vue-router';
import AuthLayout from '../components/AuthLayout.vue';

// The same answer whether or not the email has an account, like the API's.
const route = useRoute();
</script>

<template>
    <AuthLayout title="Check your inbox">
        <p class="lead" role="status">
            If there's an account for <strong>{{ route.query.email || 'that email' }}</strong>, we've sent a link to reset its password.
            It works once, for 60 minutes.
        </p>
        <p class="hint">Nothing there after a few minutes? Check your spam folder, or <RouterLink to="/forgot-password">ask for another link</RouterLink>.</p>
        <p class="after"><RouterLink to="/login">Back to log in</RouterLink></p>
    </AuthLayout>
</template>

<style scoped>
.hint {
    color: var(--muted);
}
</style>
