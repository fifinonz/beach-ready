/*
 * Beach Ready's service worker, written by hand (decision 0008). Laravel
 * serves it at /service-worker.js and fills in two values from the current
 * build: VERSION, which changes whenever the build does, and ASSETS, the
 * built files to keep for offline use.
 *
 * - The page itself (HTML) is network-first, so a new deploy is picked up
 *   on the next load; the cached copy is only for when there's no network.
 * - Built assets have content hashes in their names, so they never change
 *   and are served from the cache first.
 * - API calls, email links and Sanctum are never cached: they're always
 *   live, and fail clearly when offline.
 * - Each build gets its own cache, and older ones are deleted as soon as
 *   the new worker activates.
 */

const VERSION = __VERSION__;
const ASSETS = __ASSETS__;
const CACHE = `beach-ready-${VERSION}`;
const SHELL = '/';

const NEVER_CACHE = ['/api/', '/sanctum/', '/email/', '/up', '/service-worker.js'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll([SHELL, ...ASSETS]))
            // Take over straight away rather than waiting for every tab to close.
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(
                names
                    .filter((name) => name.startsWith('beach-ready-') && name !== CACHE)
                    .map((name) => caches.delete(name)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || NEVER_CACHE.some((path) => url.pathname.startsWith(path))) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request));
    } else if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
    }
});

/** The page: always the newest from the server, the cached shell offline. */
async function networkFirst(request) {
    try {
        const response = await fetch(request);

        // Only a good copy of the shell is worth keeping, never an error page.
        if (response.ok && response.headers.get('Content-Type')?.includes('text/html')) {
            const cache = await caches.open(CACHE);
            cache.put(SHELL, response.clone());
        }

        return response;
    } catch {
        return (await caches.match(SHELL)) ?? Response.error();
    }
}

/** Built assets: hashed names never change, so the cache can answer. */
async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(CACHE);
        cache.put(request, response.clone());
    }

    return response;
}
