<script setup>
import { useId } from 'vue';

// An on/off setting: a real button with role="switch", named by its
// visible label and described by the line under it.
const on = defineModel({ type: Boolean, required: true });
defineProps({
    label: { type: String, required: true },
    description: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const id = useId();
</script>

<template>
    <div class="setting">
        <div class="setting__text">
            <span :id="`${id}-label`" class="setting__label">{{ label }}</span>
            <span v-if="description" :id="`${id}-description`" class="setting__description">{{ description }}</span>
        </div>
        <button
            type="button"
            role="switch"
            class="switch"
            :aria-checked="on ? 'true' : 'false'"
            :aria-labelledby="`${id}-label`"
            :aria-describedby="description ? `${id}-description` : null"
            :disabled="disabled"
            @click="on = !on"
        >
            <span class="switch__thumb" />
        </button>
    </div>
</template>

<style scoped>
.setting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 0;
}

.setting + .setting {
    border-top: 1px solid var(--line);
}

.setting__text {
    display: grid;
    gap: 2px;
}

.setting__label {
    font-size: 1.0625rem;
    font-weight: 700;
}

.setting__description {
    color: var(--muted);
}

/* 52 × 44 to touch; the visible track is 52 × 32. */
.switch {
    position: relative;
    flex: none;
    width: 52px;
    height: 44px;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}

.switch::before {
    content: '';
    position: absolute;
    inset: 6px 0;
    border-radius: 999px;
    background: #b6d2cf;
    transition: background 0.15s;
}

.switch__thumb {
    position: absolute;
    top: 9px;
    left: 3px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgb(12 59 74 / 0.3);
    transition: transform 0.15s;
}

.switch[aria-checked='true']::before {
    background: var(--lagoon);
}

.switch[aria-checked='true'] .switch__thumb {
    transform: translateX(20px);
}

.switch:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

@media (prefers-reduced-motion: reduce) {
    .switch::before,
    .switch__thumb {
        transition: none;
    }
}
</style>
