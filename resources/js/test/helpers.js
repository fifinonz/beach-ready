import { vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

/** A JSON response, for stubbing fetch. */
export function jsonResponse(status, body) {
    return new Response(body === null ? null : JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

/**
 * Stubs fetch with answers by "METHOD /path". Unlisted requests get a 404.
 * Returns the mock, whose calls show what the screen sent.
 */
export function stubApi(routes) {
    const fetch = vi.fn(async (url, options = {}) => {
        const key = `${options.method ?? 'GET'} ${url.replace(/^\/api/, '').split('?')[0]}`;
        const answer = routes[key];

        if (answer === undefined) {
            return url === '/sanctum/csrf-cookie' ? new Response(null, { status: 204 }) : jsonResponse(404, { message: 'Not found' });
        }

        return typeof answer === 'function' ? answer(options) : jsonResponse(answer[0], answer[1]);
    });
    vi.stubGlobal('fetch', fetch);

    return fetch;
}

/** The JSON bodies sent with "METHOD /path". */
export function sentTo(fetch, method, path) {
    return fetch.mock.calls
        .filter(([url, options]) => url === `/api${path}` && (options?.method ?? 'GET') === method)
        .map(([, options]) => JSON.parse(options.body));
}

/** Mounts a screen inside a memory router at `path`. */
export async function mountScreen(component, path, { stubs = {} } = {}) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/:pathMatch(.*)*', component }],
    });
    await router.push(path);
    const wrapper = mount(component, { global: { plugins: [router], stubs }, attachTo: document.body });

    return { wrapper, router };
}
