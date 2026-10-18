<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { logOut, session, signedIn } from '../session.js';
import { formatHours, maskEmail } from '../time.js';
import AppLayout from '../components/AppLayout.vue';
import ModalSheet from '../components/ModalSheet.vue';
import PageHeader from '../components/PageHeader.vue';

const router = useRouter();
const user = computed(() => session.user);

const stats = ref(null);
const remindersOn = ref(null);

onMounted(async () => {
    const [allTime, reminders, email] = await Promise.allSettled([
        api('GET', '/stats/all-time'),
        api('GET', '/settings/reminders'),
        api('GET', '/settings/email'),
    ]);
    stats.value = allTime.value?.data ?? null;

    // "2 on", or "Off" when reminder emails are switched off altogether.
    if (reminders.value && email.value) {
        remindersOn.value = email.value.data.fasting_reminders
            ? `${Object.values(reminders.value.data).filter(Boolean).length} on`
            : 'Off';
    }
});

const protocol = computed(() => {
    const hours = user.value.fasting_hours ?? 16;
    return `${hours}:${24 - hours}`;
});

// "Oct", or "Oct 2025" from another year.
const memberSince = computed(() => {
    const since = new Date(user.value.member_since);
    const sameYear = since.getFullYear() === new Date().getFullYear();

    return new Intl.DateTimeFormat('en-GB', { month: 'short', year: sameYear ? undefined : 'numeric', timeZone: user.value.timezone })
        .format(since);
});

/* My why: saved when the field loses focus. */

const why = ref(user.value.why ?? '');
const whyStatus = ref(null);

async function saveWhy() {
    if (why.value === (user.value.why ?? '')) {
        return;
    }

    try {
        signedIn((await api('PATCH', '/user', { why: why.value || null })).data);
        whyStatus.value = 'Saved';
    } catch (error) {
        whyStatus.value = error instanceof OfflineError ? "Not saved: you're offline" : 'Not saved. Please try again.';
    }
}

/* Edit profile */

const editing = ref(false);
const firstName = ref('');
const editError = ref(null);

function openEdit() {
    firstName.value = user.value.first_name;
    editError.value = null;
    editing.value = true;
}

async function saveProfile() {
    try {
        signedIn((await api('PATCH', '/user', { first_name: firstName.value })).data);
        editing.value = false;
    } catch (error) {
        editError.value = error instanceof ApiError && error.status === 422
            ? error.errorFor('first_name')
            : error instanceof OfflineError ? "You're offline. Connect to the internet to save." : 'Something went wrong. Please try again.';
    }
}

async function signOut() {
    await logOut().catch(() => {});
    await router.push('/login');
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Profile" />

        <div class="profile">
            <section class="identity">
                <span class="avatar" aria-hidden="true">{{ user.first_name[0] }}</span>
                <div>
                    <p class="identity__name">{{ user.first_name }}</p>
                    <p class="identity__email">{{ maskEmail(user.email) }}</p>
                    <button type="button" class="link" @click="openEdit">Edit profile</button>
                </div>
            </section>

            <dl class="tiles">
                <div class="tile"><dt>fasts logged</dt><dd>{{ stats?.fasts_logged ?? '–' }}</dd></div>
                <div class="tile"><dt>longest fast</dt><dd>{{ stats?.longest_fast_seconds ? formatHours(stats.longest_fast_seconds) : '–' }}</dd></div>
                <div class="tile"><dt>member since</dt><dd>{{ memberSince }}</dd></div>
            </dl>

            <div class="field why">
                <label for="why"><strong>My why</strong> (optional, only you see this)</label>
                <textarea id="why" v-model="why" rows="3" maxlength="500" aria-describedby="why-status" @blur="saveWhy" @input="whyStatus = null" />
                <p id="why-status" class="why__status" aria-live="polite">{{ whyStatus }}</p>
            </div>

            <ul class="card rows">
                <li>
                    <RouterLink to="/protocol?change=next" class="row">
                        <span>Fasting protocol</span><span class="row__value">{{ protocol }}</span>
                    </RouterLink>
                </li>
                <li class="row">
                    <span>Time zone</span><span class="row__value">{{ user.timezone }}</span>
                </li>
                <li>
                    <RouterLink to="/reminders" class="row">
                        <span>Reminders</span><span class="row__value">{{ remindersOn ?? '–' }}</span>
                    </RouterLink>
                </li>
            </ul>

            <button type="button" class="link log-out" @click="signOut">Log out</button>
        </div>

        <ModalSheet v-if="editing" title="Edit profile" @close="editing = false">
            <form class="edit" @submit.prevent="saveProfile">
                <div class="field">
                    <label for="edit-first-name">First name</label>
                    <input
                        id="edit-first-name"
                        v-model="firstName"
                        type="text"
                        autocomplete="given-name"
                        autofocus
                        required
                        :aria-invalid="editError ? 'true' : null"
                        :aria-describedby="editError ? 'edit-first-name-error' : null"
                    />
                    <p v-if="editError" id="edit-first-name-error" class="field-error">{{ editError }}</p>
                </div>
                <button class="button button--block" type="submit">Save</button>
                <button class="button button--block button--outline" type="button" @click="editing = false">Cancel</button>
            </form>
        </ModalSheet>
    </AppLayout>
</template>

<style scoped>
.profile {
    display: grid;
    gap: 24px;
    padding: 8px var(--gutter) 24px;
}

.identity {
    display: flex;
    align-items: center;
    gap: 18px;
}

.avatar {
    display: grid;
    flex: none;
    place-items: center;
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: var(--lagoon);
    box-shadow: 0 0 0 4px var(--shallows);
    color: #fff;
    font-size: 2.25rem;
    font-weight: 700;
}

.identity__name {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}

.identity__email {
    color: var(--muted);
}

.link {
    min-height: 32px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--lagoon);
    font: 600 1.0625rem var(--font);
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
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
}

.why label {
    font-weight: 400;
    color: var(--muted);
}

.why label strong {
    color: var(--deep);
}

.why textarea {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid var(--line);
    border-radius: var(--radius);
    background: #fff;
    color: var(--deep);
    font: 400 1.0625rem/1.4 var(--font);
    resize: vertical;
}

.why__status {
    min-height: 1.5em;
    margin-top: -4px;
    color: var(--muted);
    font-size: 0.875rem;
}

.rows {
    margin: 0;
    padding: 0 18px;
    list-style: none;
}

.rows li + li {
    border-top: 1.5px solid var(--line);
}

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 60px;
    color: var(--deep);
    font-size: 1.0625rem;
    font-weight: 700;
    text-decoration: none;
}

.row__value {
    color: var(--muted);
    font-weight: 400;
}

.log-out {
    justify-self: center;
    min-height: 44px;
}

.edit {
    display: grid;
    gap: 14px;
}

@media (min-width: 1024px) {
    .profile {
        padding: 0;
    }
}
</style>
