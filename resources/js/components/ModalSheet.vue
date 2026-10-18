<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

/*
 * A dialog: a sheet that slides up from the bottom on phones and a centred
 * panel on desktop. It's named by its title, moves focus inside when it
 * opens, closes with Escape, and gives focus back when it closes.
 */
const props = defineProps({
    title: { type: String, required: true },
    // A darker backdrop that can hold content, like the End fast screen.
    dark: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const id = useId();
const panel = ref(null);
let returnFocusTo = null;

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }

    // Keep Tab inside the dialog.
    if (event.key === 'Tab' && panel.value) {
        const focusable = [...panel.value.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])')]
            .filter((element) => !element.disabled);
        const first = focusable[0];
        const last = focusable.at(-1);

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }
}

onMounted(async () => {
    returnFocusTo = document.activeElement;
    document.addEventListener('keydown', onKeydown);
    await nextTick();
    // The dialog itself takes focus, so nothing looks pre-chosen, unless a
    // field asks for it with `autofocus`.
    (panel.value?.querySelector('[autofocus]') ?? panel.value)?.focus();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    returnFocusTo?.focus?.();
});
</script>

<template>
    <div class="backdrop" :class="{ 'backdrop--dark': props.dark }" @click.self="emit('close')">
        <div class="backdrop__above">
            <slot name="above" />
        </div>
        <div ref="panel" class="sheet" role="dialog" aria-modal="true" tabindex="-1" :aria-labelledby="`${id}-title`">
            <span class="sheet__handle" aria-hidden="true" />
            <h2 :id="`${id}-title`" class="sheet__title">{{ title }}</h2>
            <slot />
        </div>
    </div>
</template>

<style scoped>
.backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    background: rgb(12 59 74 / 0.6);
}

.backdrop--dark {
    background: #24505d;
}

.backdrop__above {
    display: grid;
    flex: 1;
    place-items: center;
}

.sheet {
    display: grid;
    gap: 16px;
    width: 100%;
    max-height: 90dvh;
    overflow-y: auto;
    padding: 12px var(--gutter) calc(24px + env(safe-area-inset-bottom));
    border-radius: 24px 24px 0 0;
    background: var(--foam);
    animation: rise 0.2s ease-out;
}

.sheet:focus {
    outline: none;
}

.sheet__handle {
    justify-self: center;
    width: 44px;
    height: 5px;
    border-radius: 3px;
    background: var(--line);
}

.sheet__title {
    font-size: 1.75rem;
    letter-spacing: -0.02em;
}

@keyframes rise {
    from {
        transform: translateY(24px);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sheet {
        animation: none;
    }
}

@media (min-width: 768px) {
    .backdrop {
        justify-content: center;
        align-items: center;
    }

    .backdrop__above {
        flex: none;
        margin-bottom: 24px;
    }

    .sheet {
        max-width: 30rem;
        border-radius: 24px;
        padding: 24px 32px 32px;
    }

    .sheet__handle {
        display: none;
    }
}
</style>
