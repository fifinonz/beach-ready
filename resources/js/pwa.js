import { reactive } from 'vue';

/**
 * The installable app: registering the service worker, noticing when a new
 * version has taken over, and the one-time "Add to Home Screen" hint for
 * iPhones and iPads, which have no install prompt of their own.
 */
export const pwa = reactive({
    updated: false,
    showInstallHint: false,
});

const HINT_KEY = 'beach-ready:install-hint-seen';

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !import.meta.env.PROD) {
        return;
    }

    // A page that already had a worker gets a new one after a deploy. The
    // first worker taking over a fresh page isn't an update.
    const hadWorker = Boolean(navigator.serviceWorker.controller);
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (hadWorker) {
            pwa.updated = true;
        }
    });

    navigator.serviceWorker.register('/service-worker.js').catch(() => {
        // Without a worker the app still works; it just isn't available offline.
    });
}

/**
 * Whether to show the "Add to Home Screen" hint: on iOS, in the browser
 * rather than the installed app, and only until it has been dismissed.
 */
export function shouldShowInstallHint({ userAgent, maxTouchPoints, standalone, seen }) {
    const iPhoneOrIPad = /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1);

    return iPhoneOrIPad && !standalone && !seen;
}

export function checkInstallHint() {
    let seen = false;

    try {
        seen = localStorage.getItem(HINT_KEY) !== null;
    } catch {
        seen = true;
    }

    pwa.showInstallHint = shouldShowInstallHint({
        userAgent: navigator.userAgent,
        maxTouchPoints: navigator.maxTouchPoints,
        standalone: navigator.standalone === true || matchMedia('(display-mode: standalone)').matches,
        seen,
    });
}

export function dismissInstallHint() {
    pwa.showInstallHint = false;

    try {
        localStorage.setItem(HINT_KEY, '1');
    } catch {
        // Private browsing: the hint may show again next time, which is fine.
    }
}
