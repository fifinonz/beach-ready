<script setup>
import { useRoute } from 'vue-router';

// A stand-in for screens later Stage 5 sub-steps will build.
const route = useRoute();
</script>

<template>
    <main class="not-built">
        <h1>{{ route.meta.title }}</h1>
        <p>This screen is built in a later step of Stage 5.</p>
    </main>
</template>

<style scoped>
.not-built {
    display: grid;
    gap: 12px;
    max-width: 32rem;
    margin: 0 auto;
    padding: 48px var(--gutter);
}
</style>
