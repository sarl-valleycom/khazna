export class HttpError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message);
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * POST with the session and CSRF token and read a JSON answer. For the few
 * endpoints that return data instead of an Inertia page. A validation or
 * authorization failure becomes an HttpError carrying the server's message.
 */
export async function postJson<T>(url: string, payload?: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload === undefined ? undefined : JSON.stringify(payload),
        headers: {
            Accept: 'application/json',
            ...(payload === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    const body = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;

        throw new HttpError(
            response.status,
            first ?? body.message ?? 'Something went wrong. Please try again.',
        );
    }

    return body as T;
}
