import { api } from './api.js';
import { signedIn } from './session.js';
import { browserTimeZone } from './time.js';

/**
 * "Try the demo": the server makes a fresh demo account, filled with a few
 * weeks of fasts in this browser's time zone, and signs it in.
 */
export async function tryTheDemo(router) {
    const { data } = await api('POST', '/demo', { timezone: browserTimeZone() });
    signedIn(data);
    await router.push('/');
}
