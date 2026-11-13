import { router } from '@inertiajs/vue3';

export type QueryValue = string | number | boolean | null | undefined;

/**
 * Update the current URL query string and ask Inertia for the named props.
 * A null or empty value removes the key, keeping URLs free of defaults.
 */
export function updateQuery(
    params: Record<string, QueryValue>,
    only: string[] = [],
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    router.get(
        `${url.pathname}${url.search}`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            ...(only.length > 0 ? { only } : {}),
        },
    );
}
