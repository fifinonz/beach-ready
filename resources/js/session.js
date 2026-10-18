import { reactive } from 'vue';
import { api, ApiError } from './api.js';

/**
 * Who is signed in. Loaded once when the app starts, then updated by
 * signing up, logging in and logging out.
 */
export const session = reactive({
    user: null,
    loaded: false,
});

export async function loadSession() {
    try {
        session.user = (await api('GET', '/user')).data;
    } catch (error) {
        if (!(error instanceof ApiError && error.status === 401)) {
            throw error;
        }

        session.user = null;
    } finally {
        session.loaded = true;
    }
}

export function signedIn(user) {
    session.user = user;
}
