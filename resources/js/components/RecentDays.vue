<script setup>
import { formatDuration } from '../time.js';
import { fastDetails } from '../week.js';

/*
 * Fasts and rest days, newest first, from recentDays() in week.js. The
 * compact version fits one line per day (phone History, desktop Today);
 * the detailed one adds a second line with the protocol and how it felt
 * (desktop History).
 */
defineProps({
    rows: { type: Array, required: true },
    detailed: { type: Boolean, default: false },
});

const compactName = (row) => `${row.name} · ${row.fast.ended_early ? 'ended early' : row.fast.protocol}`;
const restText = (row) => (row.days > 1 ? 'Rest days' : row.streakKept ? 'Rest day · streak kept' : 'Rest day');
</script>

<template>
    <ul class="days" :class="{ 'days--detailed': detailed }">
        <li v-for="row in rows" :key="row.key" class="day">
            <template v-if="row.kind === 'fast'">
                <span v-if="detailed" class="day__name">
                    <strong>{{ row.name }}</strong>
                    <span>{{ fastDetails(row.fast) }}</span>
                </span>
                <span v-else class="day__name">{{ compactName(row) }}</span>
                <strong class="day__duration">{{ formatDuration(row.fast.duration_seconds) }}</strong>
            </template>
            <template v-else>
                <span v-if="detailed" class="day__name">
                    <strong>{{ row.name }}</strong>
                    <span>{{ restText(row) }}</span>
                </span>
                <template v-else>
                    <span class="day__name">{{ row.name }}</span>
                    <span class="day__rest">{{ restText(row) }}</span>
                </template>
            </template>
        </li>
    </ul>
</template>

<style scoped>
.days {
    margin: 0;
    padding: 0;
    list-style: none;
}

.day {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 48px;
    padding: 10px 0;
    font-size: 1.0625rem;
}

.day + .day {
    border-top: 1.5px solid var(--line);
}

.day__duration {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.day__rest {
    color: var(--muted);
}

.days--detailed .day__name {
    display: grid;
}

.days--detailed .day__name span {
    color: var(--muted);
    font-size: 0.9375rem;
}
</style>
