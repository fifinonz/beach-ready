<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from './AppIcon.vue';

// A screen's title, with the gear that opens Settings on phones. On
// desktop, Settings is in the sidebar instead.
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: null },
});
</script>

<template>
    <header class="page-header">
        <div>
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h1>{{ title }}</h1>
        </div>
        <RouterLink to="/settings" class="gear" aria-label="Settings">
            <AppIcon name="settings" />
        </RouterLink>
        <slot />
    </header>
</template>

<style scoped>
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 20px var(--gutter) 8px;
}

.eyebrow {
    margin-bottom: 4px;
    color: var(--muted);
    font-size: 1rem;
}

h1 {
    font-size: 2rem;
    letter-spacing: -0.02em;
}

.gear {
    display: grid;
    flex: none;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--shallows);
    color: var(--deep);
}

@media (min-width: 1024px) {
    .page-header {
        padding: 32px 0 20px;
    }

    h1 {
        font-size: 2.25rem;
    }

    .gear {
        display: none;
    }
}
</style>
