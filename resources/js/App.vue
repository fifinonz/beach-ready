<script setup>
import { RouterView } from 'vue-router';
import { pwa } from './pwa.js';

const reload = () => window.location.reload();
</script>

<template>
    <RouterView />

    <!-- After a deploy, the new version is already loaded in the background. -->
    <div v-if="pwa.updated" class="update" role="status">
        <span>Beach Ready has been updated.</span>
        <button type="button" class="update__reload" @click="reload">Reload</button>
    </div>
</template>

<style scoped>
/*
 * Centred along the bottom. It sizes to its content, up to the screen width
 * less a 16px gutter on each side. (With left: 50% alone, the browser would
 * squeeze it into the right half of a phone screen.)
 */
.update {
    position: fixed;
    left: 16px;
    right: 16px;
    bottom: calc(88px + env(safe-area-inset-bottom));
    z-index: 40;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: max-content;
    max-width: calc(100% - 32px);
    margin: 0 auto;
    padding: 8px 8px 8px 18px;
    border-radius: var(--radius-pill);
    background: var(--deep);
    color: #fff;
}

.update__reload {
    flex: none;
    white-space: nowrap;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: var(--radius-pill);
    background: var(--sun);
    color: var(--deep);
    font: 700 1rem var(--font);
    cursor: pointer;
}

@media (min-width: 1024px) {
    .update {
        bottom: 24px;
    }
}
</style>
