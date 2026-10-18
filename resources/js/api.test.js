import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError, OfflineError } from './api.js';
import { network } from './network.js';

function jsonResponse(status, body) {
    return new Response(body === null ? null : JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('api', () => {
    it('sends the XSRF token from the cookie with every request', async () => {
        document.cookie = 'XSRF-TOKEN=abc%3D%3D';
        const fetch = vi.fn().mockResolvedValue(jsonResponse(200, { data: 1 }));
        vi.stubGlobal('fetch', fetch);

        await api('POST', '/fasts', { fasting_hours: 16 });

        const [url, options] = fetch.mock.calls.at(-1);
        expect(url).toBe('/api/fasts');
        expect(options.headers['X-XSRF-TOKEN']).toBe('abc==');
        expect(options.credentials).toBe('same-origin');
        expect(JSON.parse(options.body)).toEqual({ fasting_hours: 16 });
    });

    it('fetches the XSRF cookie first when there is none yet', async () => {
        const fetch = vi.fn().mockResolvedValue(jsonResponse(201, { data: {} }));
        vi.stubGlobal('fetch', fetch);

        await api('POST', '/register', {});

        expect(fetch.mock.calls[0][0]).toBe('/sanctum/csrf-cookie');
        expect(fetch.mock.calls[1][0]).toBe('/api/register');
    });

    it('does not fetch the XSRF cookie for reads', async () => {
        const fetch = vi.fn().mockResolvedValue(jsonResponse(200, { data: {} }));
        vi.stubGlobal('fetch', fetch);

        await api('GET', '/user');

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('returns null for an empty response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 204 })));

        expect(await api('GET', '/anything')).toBeNull();
    });

    it('throws an ApiError with the validation messages for an error response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, {
            message: 'The email has already been taken.',
            errors: { email: ['The email has already been taken.'] },
        })));

        const error = await api('GET', '/user').catch((e) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(422);
        expect(error.errorFor('email')).toBe('The email has already been taken.');
        expect(error.errorFor('password')).toBeNull();
    });

    it('throws an OfflineError when the request never reaches the server', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        await expect(api('GET', '/user')).rejects.toBeInstanceOf(OfflineError);
    });

    it('marks the app offline when a request can\'t get through, and online again when one does', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        await api('GET', '/user').catch(() => {});
        expect(network.online).toBe(false);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, { data: {} })));
        await api('GET', '/user');
        expect(network.online).toBe(true);
    });
});
