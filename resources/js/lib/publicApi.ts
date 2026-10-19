import type { RouteDefinition } from '@/wayfinder';

type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

/** A refused or failed public API call, with the response's message and reason, if any. */
export class PublicApiError extends Error {
    constructor(
        public status: number,
        public body: {
            message?: string;
            reason?: string;
            errors?: Record<string, string[]>;
        },
    ) {
        super(body.message ?? `Request failed (${status})`);
    }
}

/**
 * Calls the public API (as headless sites do) from the hosted pages, with an optional guest
 * token (#147), and throws a PublicApiError for any non-2xx response.
 */
export async function publicApi<T>(
    route: RouteDefinition<Method>,
    options: {
        body?: Record<string, unknown>;
        guestToken?: string | null;
    } = {},
): Promise<T> {
    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.guestToken
                ? { 'X-Guest-Token': options.guestToken }
                : {}),
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    const body =
        response.status === 204 ? {} : await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new PublicApiError(response.status, body);
    }

    return body as T;
}

/**
 * A guest token kept for the browser tab, per song list. Browser storage can be unavailable
 * (e.g. private windows), so it falls back to memory.
 */
export function useGuestToken(listId: () => string | null | undefined) {
    const memory = ref<string | null>(null);
    const key = () => `dj-guest-token:${listId() ?? ''}`;

    const token = computed<string | null>({
        get: () => {
            try {
                return sessionStorage.getItem(key()) ?? memory.value;
            } catch {
                return memory.value;
            }
        },
        set: (value) => {
            memory.value = value;

            try {
                if (value) {
                    sessionStorage.setItem(key(), value);
                } else {
                    sessionStorage.removeItem(key());
                }
            } catch {
                // Kept in memory only
            }
        },
    });

    return token;
}
