<script setup>
import { computed } from 'vue';
import { formatDuration } from '../time.js';

/*
 * This week as bars, Monday to Sunday, from buildWeek() in week.js. Today
 * is the darker bar, a fast in progress is a dashed outline, and the
 * dashed line is the goal. The same numbers are in a visually hidden list
 * for screen readers.
 */
const props = defineProps({
    days: { type: Array, required: true },
    goalHours: { type: Number, required: true },
    // The desktop History chart has an hours axis and longer day names.
    showAxis: { type: Boolean, default: false },
});

const MAX_HOURS = 20;
const heightOf = (seconds) => `${Math.min(100, (seconds / 3600 / MAX_HOURS) * 100)}%`;
const goalAt = computed(() => `${(props.goalHours / MAX_HOURS) * 100}%`);

function describe(day) {
    if (day.status === 'finished') {
        return formatDuration(day.seconds);
    }

    return { 'in-progress': `${formatDuration(day.seconds)} so far, in progress`, rest: 'no fast', future: 'still to come' }[day.status];
}
</script>

<template>
    <figure class="chart" :class="{ 'chart--axis': showAxis }">
        <div class="chart__plot" aria-hidden="true">
            <template v-if="showAxis">
                <span class="chart__tick" style="bottom: 100%">20h</span>
                <span class="chart__tick" style="bottom: 50%">10h</span>
                <span class="chart__tick" style="bottom: 0">0</span>
            </template>
            <span class="chart__goal" :style="{ bottom: goalAt }" />
            <div class="chart__bars">
                <div v-for="day in days" :key="day.date" class="chart__column">
                    <span
                        class="chart__bar"
                        :class="[`is-${day.status}`, { 'is-today': day.isToday }]"
                        :style="{ height: day.seconds > 0 ? heightOf(day.seconds) : null }"
                    />
                </div>
            </div>
        </div>
        <div class="chart__labels" aria-hidden="true">
            <span v-for="day in days" :key="day.date" :class="{ 'is-today': day.isToday }">
                {{ showAxis ? day.short : day.letter }}
            </span>
        </div>
        <ul class="visually-hidden">
            <li v-for="day in days" :key="day.date">{{ day.short }}: {{ describe(day) }}</li>
        </ul>
    </figure>
</template>

<style scoped>
.chart {
    margin: 0;
}

.chart__plot {
    position: relative;
    height: 132px;
    margin-top: 24px;
}

.chart--axis .chart__plot {
    height: 300px;
    margin-left: 40px;
}

.chart__goal {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 2px dashed var(--sun);
    z-index: 1;
}

.chart__tick {
    position: absolute;
    left: -40px;
    transform: translateY(50%);
    color: var(--muted);
    font-size: 0.8125rem;
}

.chart__bars,
.chart__labels {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}

.chart__bars {
    position: absolute;
    inset: 0;
    align-items: end;
}

.chart__column {
    display: flex;
    align-items: flex-end;
    height: 100%;
}

.chart__bar {
    width: 100%;
    height: 4px;
    border-radius: 8px;
    background: var(--line);
}

.chart__bar.is-finished {
    background: var(--lagoon);
}

.chart__bar.is-finished.is-today {
    background: var(--deep);
}

.chart__bar.is-in-progress {
    border: 2px dashed var(--lagoon);
    background: var(--shallows);
}

.chart__labels {
    margin-top: 10px;
    color: var(--muted);
    font-size: 0.875rem;
    text-align: center;
}

.chart--axis .chart__labels {
    margin-left: 40px;
}

.chart__labels .is-today {
    color: var(--deep);
    font-weight: 700;
}
</style>
