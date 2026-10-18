import { reactive } from 'vue';

/**
 * Whether the device is online, for the offline banner and messages. The
 * browser's own flag is the best signal there is; requests that fail
 * anyway raise OfflineError (see api.js).
 */
export const network = reactive({ online: navigator.onLine });

window.addEventListener('online', () => (network.online = true));
window.addEventListener('offline', () => (network.online = false));
