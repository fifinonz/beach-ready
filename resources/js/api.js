/**
 * Talks to the Laravel API with Sanctum's cookie-based SPA authentication:
 * the session cookie is sent automatically, and every request that changes
 * something carries the XSRF token Laravel set in a cookie.
 */

/** The server answered with an error, such as 422 for invalid input. */
export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message ?? `Request failed with status ${status}`);
        this.status = status;
        this.errors = body?.errors ?? {};
    }

    /** The first validation message for a field, if there is one. */
    errorFor(field) {
        return this.errors[field]?.[0] ?? null;
    }
}

/** The request never reached the server, usually because the device is offline. */
export class OfflineError extends Error {
    constructor() {
        super("You're offline. Connect to the internet and try again.");
    }
}

function cookie(name) {
    const match = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

/** Sanctum sets the XSRF-TOKEN cookie; fetch it once before the first change. */
async function ensureXsrfCookie() {
    if (cookie('XSRF-TOKEN') === null) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
    }
}

/**
 * Sends a request to /api and returns the parsed JSON, or null for an empty
 * response. Throws ApiError for error responses and OfflineError when the
 * network is unreachable.
 */
export async function api(method, path, body = undefined) {
    let response;

    try {
        if (method !== 'GET') {
            await ensureXsrfCookie();
        }

        response = await fetch(`/api${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': cookie('XSRF-TOKEN') ?? '',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new OfflineError();
    }

    const data = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data;
}
