<script setup>
import { computed, ref } from 'vue';
import { api, OfflineError } from '../api.js';
import { elapsedSeconds, formatClock, formatDuration } from '../time.js';
import ModalSheet from './ModalSheet.vue';

/*
 * "End your fast now?" Ending early is normal: the fast is saved to history
 * like any other, and nothing here suggests otherwise.
 */
const props = defineProps({
    fast: { type: Object, required: true },
    now: { type: Number, required: true },
});
const emit = defineEmits(['close', 'ended']);

const feelings = ['great', 'okay', 'hungry', 'unwell'];
const feeling = ref(null);
const busy = ref(false);
const errorMessage = ref(null);

const elapsed = computed(() => elapsedSeconds(props.fast.started_at, props.now));
const reachedGoal = computed(() => props.now >= Date.parse(props.fast.target_end_at));

// Pressing the chosen feeling again clears it: it's optional.
function choose(value) {
    feeling.value = feeling.value === value ? null : value;
}

async function endFast() {
    busy.value = true;
    errorMessage.value = null;

    try {
        const { data } = await api('POST', '/fasts/active/end', { feeling: feeling.value });
        emit('ended', data);
    } catch (error) {
        errorMessage.value = error instanceof OfflineError
            ? "You're offline. Your fast is still running; end it once you're connected."
            : 'Something went wrong. Please try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <ModalSheet :title="reachedGoal ? 'End your fast?' : 'End your fast now?'" dark @close="emit('close')">
        <template #above>
            <div class="running" aria-hidden="true">
                <p>You're fasting</p>
                <p class="running__clock">{{ formatClock(elapsed) }}</p>
            </div>
        </template>

        <p class="summary">
            <template v-if="reachedGoal">You've fasted {{ formatDuration(elapsed) }} and reached your goal. It's saved to your history.</template>
            <template v-else>You've fasted {{ formatDuration(elapsed) }}. That still counts, and it's saved to your history either way.</template>
        </p>

        <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

        <div class="feelings">
            <p id="feeling-question" class="feelings__question"><strong>How are you feeling?</strong> (optional)</p>
            <div class="feelings__chips" role="group" aria-labelledby="feeling-question">
                <button
                    v-for="option in feelings"
                    :key="option"
                    type="button"
                    class="chip"
                    :aria-pressed="feeling === option ? 'true' : 'false'"
                    @click="choose(option)"
                >
                    {{ option[0].toUpperCase() + option.slice(1) }}
                </button>
            </div>
        </div>

        <button class="button button--block" type="button" :disabled="busy" @click="endFast">
            {{ busy ? 'Saving…' : 'Yes, end my fast' }}
        </button>
        <button class="button button--block button--outline" type="button" @click="emit('close')">Keep going</button>
    </ModalSheet>
</template>

<style scoped>
.running {
    color: #e1f3f0;
    text-align: center;
}

.running p:first-child {
    font-size: 1.0625rem;
    font-weight: 600;
}

.running__clock {
    font-size: 3.5rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.summary {
    color: var(--muted);
    font-size: 1.0625rem;
}

.feelings {
    display: grid;
    gap: 12px;
}

.feelings__question {
    color: var(--muted);
}

.feelings__question strong {
    color: var(--deep);
}

.feelings__chips {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.chip {
    min-height: 44px;
    padding: 8px 4px;
    border: 1.5px solid var(--line);
    border-radius: var(--radius-pill);
    background: #fff;
    color: var(--deep);
    font: 600 1rem var(--font);
    cursor: pointer;
}

.chip[aria-pressed='true'] {
    border: 2px solid var(--lagoon);
    background: var(--shallows);
}
</style>
