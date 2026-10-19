<script setup>
import BrandMark from './BrandMark.vue';

// The frame for screens before signing in, in the sign-up screen's style.
defineProps({ title: { type: String, required: true } });
</script>

<template>
    <main class="auth">
        <BrandMark />
        <h1>{{ title }}</h1>
        <slot />
    </main>
</template>

<style scoped>
.auth {
    display: grid;
    gap: 28px;
    max-width: 30rem;
    margin: 0 auto;
    padding: 28px var(--gutter) 32px;
}

h1 {
    font-size: 2.25rem;
    letter-spacing: -0.02em;
}

.auth :deep(form) {
    display: grid;
    gap: 22px;
}

.auth :deep(form .button) {
    margin-top: 12px;
}

.auth :deep(.lead) {
    margin-top: -12px;
    color: var(--muted);
    font-size: 1.0625rem;
}

.auth :deep(.after) {
    text-align: center;
    font-size: 1.0625rem;
}
</style>
