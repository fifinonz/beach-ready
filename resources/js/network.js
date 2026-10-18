import { reactive } from 'vue';

/**
 * Whether the device is online, for the offline banner and messages. It
 * starts from the browser's own flag and follows its online and offline
 * events; api.js also updates it whenever a request gets through or can't.
 */
export const network = reactive({ online: navigator.onLine });

window.addEventListener('online', () => (network.online = true));
window.addEventListener('offline', () => (network.online = false));
