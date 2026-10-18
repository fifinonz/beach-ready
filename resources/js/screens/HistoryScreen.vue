<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, OfflineError } from '../api.js';
import { session } from '../session.js';
import { formatDuration, formatHours } from '../time.js';
import { buildWeek, recentDays } from '../week.js';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import RecentDays from '../components/RecentDays.vue';
import WeekChart from '../components/WeekChart.vue';

const route = useRoute();

const stats = ref(null);
const fasts = ref([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(true);
const errorMessage = ref(null);

const timeZone = computed(() => session.user.timezone);
const goalHours = computed(() => session.user.fasting_hours ?? 16);
const now = Date.now();

// Shown after ending a fast: "Fast saved: 11h 24m. Nice work."
const saved = computed(() => (route.query.saved ? formatDuration(Number(route.query.saved)) : null));

onMounted(async () => {
    try {
        const [week] = await Promise.all([api('GET', '/stats/week'), loadMore()]);
        stats.value = week.data;
    } catch (error) {
        errorMessage.value = error instanceof OfflineError
            ? "You're offline. Your history will show up once you're connected."
            : 'Something went wrong loading your history.';
    } finally {
        loading.value = false;
    }
});

async function loadMore() {
    const response = await api('GET', `/fasts?page=${page.value + 1}`);
    fasts.value = [...fasts.value, ...response.data];
    page.value = response.meta.current_page;
    lastPage.value = response.meta.last_page;
}

const week = computed(() => buildWeek({ fasts: fasts.value, active: null, timeZone: timeZone.value, now }));
const rows = computed(() => recentDays({ fasts: fasts.value, timeZone: timeZone.value, now }));
const streakDays = computed(() => stats.value?.current_streak_days ?? 0);
</script>

<template>
    <AppLayout wide>
        <PageHeader title="History">
            <p v-if="saved" class="saved saved--desktop" role="status">
                <AppIcon name="check" /><span><strong>Fast saved: {{ saved }}.</strong> Nice work.</span>
            </p>
        </PageHeader>

        <div class="history">
            <p v-if="saved" class="saved saved--phone" role="status">
                <AppIcon name="check" /><span><strong>Fast saved: {{ saved }}.</strong> Nice work.</span>
            </p>

            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <template v-if="!loading && !errorMessage">
                <dl class="tiles">
                    <div class="tile">
                        <dt>fasts this week</dt>
                        <dd>{{ stats.fasts_this_week }}</dd>
                    </div>
                    <div class="tile">
                        <dt>average fast</dt>
                        <dd>{{ stats.average_fast_seconds ? formatHours(stats.average_fast_seconds) : '–' }}</dd>
                    </div>
                    <div class="tile">
                        <dt>current streak<span class="tile__note"> · a rest day won't break it</span></dt>
                        <dd>{{ streakDays }} {{ streakDays === 1 ? 'day' : 'days' }}</dd>
                    </div>
                </dl>

                <div class="panels">
                    <section class="card chart-card" aria-labelledby="this-week">
                        <div class="chart-card__head">
                            <h2 id="this-week">This week</h2>
                            <span>Dashed line: {{ goalHours }}h goal</span>
                        </div>
                        <WeekChart class="chart--phone" :days="week" :goal-hours="goalHours" />
                        <WeekChart class="chart--desktop" :days="week" :goal-hours="goalHours" show-axis />
                    </section>

                    <section class="recent" aria-labelledby="recent-fasts">
                        <h2 id="recent-fasts" class="recent__title">Recent fasts</h2>
                        <p v-if="rows.length === 0" class="empty">Your finished fasts will show up here.</p>
                        <RecentDays class="days--phone" :rows="rows" />
                        <RecentDays class="days--desktop" :rows="rows" detailed />
                        <button v-if="page < lastPage" type="button" class="more" @click="loadMore">Show older fasts</button>
                    </section>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.history {
    display: grid;
    gap: 20px;
    padding: 8px var(--gutter) 24px;
}

.saved {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: var(--radius);
    background: var(--shallows);
    font-size: 1.0625rem;
}

.saved .icon {
    flex: none;
    color: var(--lagoon);
}

.saved--desktop {
    display: none;
}

.tiles {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 0;
}

.tile {
    display: flex;
    flex-direction: column-reverse;
    justify-content: flex-end;
    padding: 12px 12px 14px;
    border-radius: var(--radius);
    background: var(--deep);
    color: #fff;
}

.tile dd {
    margin: 0;
    color: var(--sun);
    font-size: 1.625rem;
    font-weight: 700;
    line-height: 1.2;
}

.tile dt {
    font-size: 0.875rem;
    line-height: 1.25;
}

.tile__note {
    display: none;
}

.panels {
    display: grid;
    gap: 20px;
}

.chart-card {
    padding: 16px 18px 18px;
}

.chart-card__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.chart-card__head h2 {
    font-size: 1.25rem;
}

.chart-card__head span {
    color: var(--muted);
    font-size: 0.875rem;
}

.chart--desktop,
.days--desktop {
    display: none;
}

.recent__title {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
}

.empty {
    color: var(--muted);
}

.more {
    min-height: 44px;
    margin-top: 8px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--lagoon);
    font: 600 1rem var(--font);
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
}

/* Desktop, as in 11-desktop-history.png. */
@media (min-width: 1024px) {
    .history {
        padding: 0;
    }

    .saved--phone {
        display: none;
    }

    .saved--desktop {
        display: flex;
        margin-top: 8px;
    }

    .tiles {
        gap: 24px;
    }

    .tile {
        padding: 22px 30px;
        border-radius: 20px;
    }

    .tile dd {
        font-size: 2.5rem;
    }

    .tile dt {
        font-size: 1.0625rem;
    }

    .tile__note {
        display: inline;
    }

    .panels {
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
        align-items: stretch;
        gap: 24px;
    }

    .chart-card {
        padding: 24px 28px;
        border-radius: 20px;
    }

    .chart--phone,
    .days--phone {
        display: none;
    }

    .chart--desktop,
    .days--desktop {
        display: block;
    }

    .recent {
        padding: 24px 28px;
        border: 1.5px solid var(--line);
        border-radius: 20px;
        background: #fff;
    }

    .recent__title {
        position: static;
        width: auto;
        height: auto;
        clip-path: none;
        margin-bottom: 8px;
        font-size: 1.375rem;
    }
}
</style>
