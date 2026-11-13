const DISPLAY_TIMEZONE = 'Africa/Algiers';

const moneyFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: DISPLAY_TIMEZONE,
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: DISPLAY_TIMEZONE,
});

/**
 * Format integer centimes as English money text, e.g. 1000050 => "10,000.50 DA".
 */
export function formatMoney(cents: number, symbol = 'DA'): string {
    return `${moneyFormatter.format(cents / 100)} ${symbol}`;
}

export function formatInteger(value: number): string {
    return integerFormatter.format(value);
}

/**
 * Format an Algiers calendar date (YYYY-MM-DD) as "13 Nov 2026".
 */
export function formatDate(value: string | Date): string {
    const date =
        typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)
            ? new Date(`${value}T12:00:00Z`)
            : new Date(value);

    return dateFormatter.format(date);
}

/**
 * Format a UTC timestamp in Algiers time as "13 Nov 2026, 14:30".
 */
export function formatDateTime(value: string | Date): string {
    return dateTimeFormatter.format(new Date(value));
}

/**
 * Format a Date as an Algiers calendar date (YYYY-MM-DD).
 */
export function toCalendarDate(value: Date): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: DISPLAY_TIMEZONE,
    }).formatToParts(value);

    const get = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${get('year')}-${get('month')}-${get('day')}`;
}

export { DISPLAY_TIMEZONE };

/**
 * Parse a typed amount back to integer centimes.
 *
 * Accepts spaces, dots and commas as grouping or decimal separators. The last
 * separator is decimal only when it is followed by one or two digits; a
 * separator followed by three digits is treated as a grouping separator.
 * Returns null for empty input and NaN for text with no digits.
 */
export function parseMoneyInput(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const negative = trimmed.startsWith('-');
    const cleaned = trimmed.replace(/[^\d.,]/g, '');

    if (!/\d/.test(cleaned)) {
        return Number.NaN;
    }

    const lastSeparator = Math.max(
        cleaned.lastIndexOf(','),
        cleaned.lastIndexOf('.'),
    );

    let whole: string;
    let fraction = '';

    if (lastSeparator === -1) {
        whole = cleaned;
    } else {
        const after = cleaned.slice(lastSeparator + 1);

        if (/^\d{1,2}$/.test(after)) {
            whole = cleaned.slice(0, lastSeparator);
            fraction = after.padEnd(2, '0');
        } else {
            whole = cleaned;
        }
    }

    const wholeDigits = whole.replace(/\D/g, '') || '0';
    const cents = Number(wholeDigits) * 100 + Number(fraction || '0');

    return negative ? -cents : cents;
}
