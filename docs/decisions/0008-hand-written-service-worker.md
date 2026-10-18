# 8. A hand-written service worker that a new deploy always replaces

## Context

Beach Ready installs as a PWA: a manifest, icons, and a service worker that keeps the app shell available so the timer still runs offline. A service worker can do lasting harm, though. If it serves an old cached shell, people keep running an old version of the app long after a deploy, and nothing on the server can reach them.

Plugins such as `vite-plugin-pwa` generate a worker with Workbox. That works, but it adds a large dependency and generated code that reviewers can't read, for what this app needs: one shell and a handful of built files.

## Decision

A short service worker written by hand (`resources/service-worker/service-worker.js`), served by Laravel at `/service-worker.js` with two values filled in from the current build.

- **One cache per build.** `VERSION` is a hash of the build's Vite manifest and font manifest, so any build that changes a file produces a new version and a new cache name, `beach-ready-<version>`. Browsers compare the worker byte for byte, so a new version is a new worker. The response has `Cache-Control: no-cache, no-store`, so browsers always check for it.
- **The HTML is network-first.** Every page load asks the server for the shell first and only falls back to the cached copy with no network. A deploy is therefore picked up on the next load, even before the new worker has finished installing. Only successful HTML is cached, never an error page.
- **Built assets are cache-first.** Their names contain content hashes, so a cached file can never be out of date; a new build references new names.
- **The API is never cached.** `/api/`, `/sanctum/`, `/email/` and `/up` always go to the network. Offline, those requests fail and the app says so clearly; it never shows stale data as if it were current.
- **Old caches are deleted on activation.** The new worker calls `skipWaiting()` and `clients.claim()` to take over straight away, then deletes every `beach-ready-*` cache except its own.
- **Open tabs are told.** A tab that was open during a deploy keeps running the old JavaScript until it reloads, so it shows "Beach Ready has been updated. Reload".
- **Offline.** The installed app opens from the cache. The signed-in user and the active fast are kept on the device, so the timer keeps running, calculated from the start time as always. Anything that needs the server shows "You're offline", and the offline banner also appears when a request can't get through, because the browser's own online flag can be wrong.
- **iOS.** iPhones and iPads have no install prompt, so a one-time hint explains Share, then Add to Home Screen. It doesn't appear in the installed app, and it doesn't appear again once dismissed.

## How a deploy is checked

Automated (`tests/Feature/ServiceWorkerTest.php`): the worker is served with no-cache headers and lists every built file; its version changes when the build changes and stays the same when it doesn't; it works before anything is built; and it never caches the API.

By hand, after a deploy (also in the Stage 5 QA checklist):

1. Open the app, signed in, and note the version: in DevTools, Application → Cache storage shows `beach-ready-<version>`.
2. Deploy a change, for example a visible text change.
3. Reload once. The change shows, Cache storage has only the new `beach-ready-<version>`, and Application → Service workers shows the new worker activated.
4. With a tab left open from before the deploy, "Beach Ready has been updated. Reload" appears, and Reload shows the change.
5. In DevTools, Network → Offline, then reload: the app opens, the timer keeps running and the offline banner shows.

This was run against Lando while building it: a rebuild followed by a single reload loaded the new JavaScript, and only the new cache remained.

## Trade-off

- **No precache manifest tooling.** The asset list comes from Vite's manifest at request time, so it's always right for the deployed build. But it means the worker is a Laravel route rather than a static file, and it must be listed before the Vue catch-all route.
- **Taking over immediately.** `skipWaiting()` means a new worker serves a tab that loaded under the old one. That's safe here, because the HTML is network-first and assets are versioned by name. The open tab keeps its old JavaScript until the reload the notice suggests.
- **Offline is read-only.** Starting, editing and ending fasts need the server; queuing changes for later would mean conflict handling the app doesn't need yet.
