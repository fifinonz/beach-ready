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
.update {
    position: fixed;
    left: 50%;
    bottom: calc(88px + env(safe-area-inset-bottom));
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: calc(100% - 32px);
    padding: 10px 10px 10px 18px;
    border-radius: var(--radius-pill);
    background: var(--deep);
    color: #fff;
    transform: translateX(-50%);
}

.update__reload {
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
