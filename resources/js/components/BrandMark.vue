<template>
    <span class="brand">
        <span class="brand__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="13" r="8" />
                <path d="M12 9v4l2.5 2.5M10 2h4" />
            </svg>
        </span>
        <span class="brand__name">Beach Ready</span>
    </span>
</template>

<style scoped>
.brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.brand__icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--lagoon);
    color: #fff;
}

.brand__icon svg {
    width: 20px;
    height: 20px;
}

.brand__name {
    font-size: 1.375rem;
    font-weight: 700;
    letter-spacing: -0.01em;
}
</style>
