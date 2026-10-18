<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from './AppIcon.vue';
import BrandMark from './BrandMark.vue';
import { network } from '../network.js';

// Today and History use the full width on desktop. Other screens sit in a
// centred column beside the sidebar.
defineProps({ wide: { type: Boolean, default: false } });

/*
 * The frame around every signed-in screen. Phones get a tab bar at the
 * bottom; from 1024px wide it becomes a sidebar on the left, with Settings
 * at the bottom. On phones, Settings is the gear in each screen's header.
 */
const tabs = [
    { to: '/', label: 'Today', icon: 'today' },
    { to: '/history', label: 'History', icon: 'history' },
    { to: '/reminders', label: 'Reminders', icon: 'reminders' },
    { to: '/profile', label: 'Profile', icon: 'profile' },
];
</script>

<template>
    <div class="layout">
        <nav class="nav" aria-label="Main">
            <RouterLink to="/" class="nav__brand"><BrandMark /></RouterLink>
            <ul>
                <li v-for="tab in tabs" :key="tab.to">
                    <RouterLink :to="tab.to" class="nav__link" exact-active-class="is-active">
                        <AppIcon :name="tab.icon" />
                        <span>{{ tab.label }}</span>
                    </RouterLink>
                </li>
            </ul>
            <RouterLink to="/settings" class="nav__link nav__settings" exact-active-class="is-active">
                <AppIcon name="settings" />
                <span>Settings</span>
            </RouterLink>
        </nav>

        <div class="content" :class="{ 'content--narrow': !wide }">
            <p v-if="!network.online" class="offline" role="status">
                <AppIcon name="offline" />
                You're offline. Your timer keeps running; saving changes needs a connection.
            </p>
            <slot />
        </div>
    </div>
</template>

<style scoped>
.layout {
    min-height: 100dvh;
}

.content {
    max-width: 30rem;
    margin: 0 auto;
    /* Room for the fixed tab bar. */
    padding-bottom: calc(96px + env(safe-area-inset-bottom));
}

.offline {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 12px var(--gutter) 0;
    padding: 12px 16px;
    border-radius: var(--radius);
    background: #fdecc8;
}

.offline .icon {
    flex: none;
}

/* Phones and tablets: a tab bar along the bottom. */
.nav {
    position: fixed;
    inset: auto 0 0;
    z-index: 10;
    border-top: 1px solid var(--line);
    background: #fff;
    padding-bottom: env(safe-area-inset-bottom);
}

.nav ul {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin: 0;
    padding: 0;
    list-style: none;
}

.nav .nav__brand,
.nav .nav__settings {
    display: none;
}

.nav__link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-height: 64px;
    padding: 10px 4px 12px;
    color: var(--muted);
    font-weight: 400;
    font-size: 0.875rem;
    text-decoration: none;
}

.nav__link.is-active {
    color: var(--lagoon);
    font-weight: 700;
}

/* Desktop: the tab bar becomes a sidebar. */
@media (min-width: 1024px) {
    .layout {
        display: grid;
        grid-template-columns: 240px 1fr;
    }

    .nav {
        position: sticky;
        top: 0;
        display: flex;
        flex-direction: column;
        height: 100dvh;
        padding: 28px 16px 24px;
        border-top: 0;
        border-right: 1px solid var(--line);
        background: var(--foam);
    }

    .nav .nav__brand {
        display: block;
        margin: 0 0 32px 12px;
        color: var(--deep);
        text-decoration: none;
    }

    .nav ul {
        display: grid;
        grid-template-columns: none;
        gap: 4px;
    }

    .nav__link {
        flex-direction: row;
        gap: 14px;
        min-height: 48px;
        padding: 10px 14px;
        border-radius: 12px;
        color: var(--deep);
        font-size: 1.0625rem;
    }

    .nav__link.is-active {
        background: var(--shallows);
        color: var(--deep);
    }

    .nav .nav__settings {
        display: flex;
        margin-top: auto;
    }

    .content {
        max-width: none;
        margin: 0;
        padding: 0 48px 48px;
    }

    .content--narrow > :deep(*) {
        max-width: 36rem;
        margin-left: auto;
        margin-right: auto;
    }
}
</style>
