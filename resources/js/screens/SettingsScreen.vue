<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError, OfflineError } from '../api.js';
import { session, signedOut } from '../session.js';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import ModalSheet from '../components/ModalSheet.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';

const router = useRouter();

const topics = [
    { key: 'fasting_reminders', label: 'Fasting reminders', description: 'Only the ones you set up' },
    { key: 'weekly_summary', label: 'Weekly summary', description: 'Sunday evening, one email' },
    { key: 'product_news', label: 'Product news', description: 'New features, a few times a year' },
];

const email = ref(null);
const errorMessage = ref(null);
const status = ref(null);

onMounted(async () => {
    try {
        email.value = (await api('GET', '/settings/email')).data;
    } catch (error) {
        errorMessage.value = messageFor(error);
    }
});

function messageFor(error) {
    return error instanceof OfflineError
        ? "You're offline. Changes to settings need a connection."
        : 'Something went wrong. Please try again.';
}

async function saveTopics(changes) {
    const before = { ...email.value };
    Object.assign(email.value, changes);
    errorMessage.value = null;
    status.value = null;

    try {
        email.value = (await api('PATCH', '/settings/email', changes)).data;
    } catch (error) {
        email.value = before;
        errorMessage.value = messageFor(error);
        throw error;
    }
}

async function unsubscribeFromAll() {
    await saveTopics({ fasting_reminders: false, weekly_summary: false, product_news: false }).catch(() => null);

    if (!errorMessage.value) {
        status.value = "You're unsubscribed from all emails. You can turn any of them back on here.";
    }
}

const confirmedAndOn = computed(() => email.value?.email_confirmed && email.value.fasting_reminders);

/* Deleting the account */

const deleting = ref(false);
const password = ref('');
const deleteError = ref(null);
const deleteBusy = ref(false);

function openDelete() {
    password.value = '';
    deleteError.value = null;
    deleting.value = true;
}

async function deleteAccount() {
    deleteBusy.value = true;
    deleteError.value = null;

    try {
        await api('DELETE', '/account', { password: password.value });
        signedOut();
        await router.push('/signup');
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            deleteError.value = 'That password is not right.';
        } else if (error instanceof ApiError && error.status === 429) {
            deleteError.value = 'Too many attempts. Please wait a minute and try again.';
        } else {
            deleteError.value = messageFor(error);
        }
    } finally {
        deleteBusy.value = false;
    }
}
</script>

<template>
    <AppLayout :tabs="false">
        <header class="header">
            <button type="button" class="back" aria-label="Back" @click="router.back()">
                <AppIcon name="back" />
            </button>
            <h1>Settings</h1>
        </header>

        <div class="settings">
            <p v-if="confirmedAndOn" class="banner" role="status">
                <AppIcon name="check" /> Email confirmed. Reminders are on.
            </p>

            <p v-if="errorMessage" class="form-alert" role="alert">{{ errorMessage }}</p>

            <section v-if="email" aria-labelledby="emails-title">
                <h2 id="emails-title">Emails from Beach Ready</h2>
                <div class="card group">
                    <ToggleSwitch
                        v-for="topic in topics"
                        :key="topic.key"
                        :model-value="email[topic.key]"
                        :label="topic.label"
                        :description="topic.description"
                        @update:model-value="(on) => saveTopics({ [topic.key]: on }).catch(() => null)"
                    />
                </div>
                <button type="button" class="button button--block button--outline unsubscribe" @click="unsubscribeFromAll">
                    Unsubscribe from all emails
                </button>
                <p class="status" aria-live="polite">{{ status }}</p>
            </section>

            <section aria-labelledby="data-title">
                <h2 id="data-title">Your data</h2>
                <ul class="card group data">
                    <li>
                        <a class="data__row" href="/api/account/export" download>
                            <strong>Download my data</strong><span>JSON</span>
                        </a>
                    </li>
                    <li v-if="session.user?.is_demo" class="data__row">
                        <strong>Delete my account</strong><span>Demo accounts delete themselves after 24 hours</span>
                    </li>
                    <li v-else>
                        <button type="button" class="data__row data__row--danger" @click="openDelete">
                            <strong>Delete my account</strong><span>Removes everything</span>
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <ModalSheet v-if="deleting" title="Delete your account?" @close="deleting = false">
            <form class="delete" @submit.prevent="deleteAccount">
                <p>This deletes your account, every fast, your reminders and your email settings. It can't be undone. You can download your data first.</p>
                <div class="field">
                    <label for="delete-password">Your password</label>
                    <input
                        id="delete-password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        autofocus
                        required
                        :aria-invalid="deleteError ? 'true' : null"
                        :aria-describedby="deleteError ? 'delete-password-error' : null"
                    />
                    <p v-if="deleteError" id="delete-password-error" class="field-error">{{ deleteError }}</p>
                </div>
                <button class="button button--block button--danger" type="submit" :disabled="deleteBusy || !password">
                    {{ deleteBusy ? 'Deleting…' : 'Delete my account' }}
                </button>
                <button class="button button--block button--outline" type="button" @click="deleting = false">Keep my account</button>
            </form>
        </ModalSheet>
    </AppLayout>
</template>

<style scoped>
.header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px var(--gutter) 8px;
}

.header h1 {
    font-size: 2rem;
    letter-spacing: -0.02em;
}

.back {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 50%;
    background: var(--shallows);
    color: var(--deep);
    cursor: pointer;
}

.settings {
    display: grid;
    gap: 28px;
    padding: 8px var(--gutter) 24px;
}

.banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: var(--radius);
    background: var(--shallows);
    font-size: 1.0625rem;
    font-weight: 600;
}

h2 {
    margin-bottom: 12px;
    font-size: 1.0625rem;
}

.group {
    padding: 0 20px;
}

.unsubscribe {
    margin-top: 16px;
    border-color: var(--deep);
    color: var(--deep);
}

.status {
    margin-top: 8px;
    color: var(--muted);
}

.status:empty {
    display: none;
}

.data {
    margin: 0;
    list-style: none;
}

.data li + li {
    border-top: 1.5px solid var(--line);
}

.data__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    min-height: 64px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--deep);
    font: 400 1rem var(--font);
    text-decoration: none;
    cursor: pointer;
}

.data__row strong {
    font-size: 1.125rem;
}

.data__row span {
    color: var(--muted);
}

.data__row--danger strong {
    color: var(--danger);
}

.delete {
    display: grid;
    gap: 14px;
}

.delete > p {
    color: var(--muted);
}

.button--danger {
    border-color: var(--danger);
    background: var(--danger);
}

@media (min-width: 1024px) {
    .header {
        padding: 32px 0 20px;
    }

    .settings {
        padding: 0;
    }
}
</style>
