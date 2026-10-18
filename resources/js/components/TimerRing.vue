<script setup>
import { computed } from 'vue';
import { elapsedSeconds, formatClock, formatDuration, formatToGo, progress } from '../time.js';

/*
 * The live timer. Everything is worked out from the fast's start and goal
 * times and `now`, which the Today screen refreshes every second; nothing
 * here counts.
 */
const props = defineProps({
    fast: { type: Object, required: true },
    now: { type: Number, required: true },
});

const RADIUS = 88;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const elapsed = computed(() => elapsedSeconds(props.fast.started_at, props.now));
const toGo = computed(() => formatToGo(props.fast.target_end_at, props.now));
const dashOffset = computed(() => CIRCUMFERENCE * (1 - progress(props.fast.started_at, props.fast.target_end_at, props.now)));
// For screen readers: changes once a minute, not every second.
const summary = computed(() => `Elapsed ${formatDuration(elapsed.value)}. ${toGo.value}.`);
</script>

<template>
    <div class="ring">
        <svg viewBox="0 0 200 200" aria-hidden="true">
            <circle class="ring__track" cx="100" cy="100" :r="RADIUS" />
            <circle
                class="ring__progress"
                cx="100"
                cy="100"
                :r="RADIUS"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="dashOffset"
            />
        </svg>
        <div class="ring__centre">
            <p class="ring__label" aria-hidden="true">Elapsed</p>
            <p class="ring__clock" aria-hidden="true">{{ formatClock(elapsed) }}</p>
            <p class="ring__to-go" aria-hidden="true">{{ toGo }}</p>
            <p class="visually-hidden">{{ summary }}</p>
        </div>
    </div>
</template>

<style scoped>
.ring {
    position: relative;
    width: min(100%, 272px);
    aspect-ratio: 1;
    margin: 0 auto;
}

svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

circle {
    fill: none;
    stroke-width: 18;
}

.ring__track {
    stroke: var(--shallows);
}

.ring__progress {
    stroke: var(--lagoon);
    stroke-linecap: round;
}

.ring__centre {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 2px;
}

.ring__label {
    color: var(--muted);
    font-weight: 600;
}

.ring__clock {
    font-size: 3rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.ring__to-go {
    padding: 4px 14px;
    border-radius: var(--radius-pill);
    background: var(--sun);
    font-weight: 700;
}

@media (min-width: 1024px) {
    .ring {
        width: min(100%, 300px);
    }
}
</style>
