import { reactive } from 'vue';
import { api, ApiError, OfflineError } from './api.js';
import { browserTimeZone } from './time.js';

/**
 * Who is signed in. Loaded once when the app starts, then updated by
 * signing up, logging in, editing the profile and logging out.
 *
 * The last known user is also kept on the device, so the installed app can
 * open offline and keep showing the timer.
 */
export const session = reactive({
    user: null,
    loaded: false,
});

const STORAGE_KEY = 'beach-ready:user';

// Everything cached about the account, removed when signing out. Device
// preferences, such as having seen the install hint, stay.
const ACCOUNT_KEYS = [STORAGE_KEY, 'beach-ready:active-fast'];

export async function loadSession() {
    try {
        signedIn((await api('GET', '/user')).data);
        await followPhoneTimeZone();
    } catch (error) {
        if (error instanceof OfflineError) {
            session.user = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
        } else if (error instanceof ApiError && error.status === 401) {
            signedOut();
        } else {
            throw error;
        }
    } finally {
        session.loaded = true;
    }
}

export function signedIn(user) {
    session.user = user;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
}

export function signedOut() {
    session.user = null;
    ACCOUNT_KEYS.forEach((key) => localStorage.removeItem(key));
}

export async function logOut() {
    await api('POST', '/logout');
    signedOut();
}

/**
 * Reminders and "today" follow the phone: when its time zone changes, for
 * example after a flight, the profile changes with it.
 */
async function followPhoneTimeZone() {
    const timezone = browserTimeZone();

    if (session.user && session.user.timezone !== timezone) {
        signedIn((await api('PATCH', '/user', { timezone })).data);
    }
}
