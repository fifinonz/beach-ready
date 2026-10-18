<script setup>
// The interface icons, drawn as 24px strokes. Decorative: the text next to
// each icon, or the button's aria-label, says what it means.
defineProps({ name: { type: String, required: true } });

const paths = {
    today: 'M12 13V9.5M10 2.5h4M12 21a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z',
    history: 'M5 20V12M12 20V5M19 20v-6',
    reminders: 'M18 16V11a6 6 0 1 0-12 0v5l-1.5 2h15ZM10 21a2 2 0 0 0 4 0',
    profile: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z',
    back: 'M15 5l-7 7 7 7',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 11v5M12 8h.01',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
    mail: 'M4 6h16v12H4zM4 7l8 6 8-6',
    offline: 'M2 2l20 20M8.5 16.4a5 5 0 0 1 7 0M5 12.6a10 10 0 0 1 5.2-2.8M19 12.6a10 10 0 0 0-2.2-1.6M1.4 9a15 15 0 0 1 4.3-2.8M22.6 9A15 15 0 0 0 12 5c-.7 0-1.4 0-2 .1M12 20h.01',
    share: 'M12 15V3M8 7l4-4 4 4M5 12v8h14v-8',
};
</script>

<template>
    <svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path :d="paths[name]" />
    </svg>
</template>
