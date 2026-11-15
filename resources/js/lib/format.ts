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

/**
 * Format a Date as an Algiers datetime-local value (YYYY-MM-DDTHH:MM).
 */
export function toDateTimeInput(value: Date): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: DISPLAY_TIMEZONE,
    }).formatToParts(value);

    const get = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

export { DISPLAY_TIMEZONE };

function isGroupedInteger(value: string, grouping: string): boolean {
    const separator = grouping === '.' ? '\\.' : ',';

    return new RegExp(`^[1-9]\\d{0,2}(${separator}\\d{3})+$`).test(value);
}

/**
 * Parse a typed amount back to integer centimes. Mirrors App\Support\Money::parse
 * so the browser and the server always agree.
 *
 * Accepts an optional "DA" suffix, spaces, apostrophes, dots and commas. A last
 * separator followed by one or two digits is the decimal mark. A separator
 * followed by exactly three digits is only accepted as thousands grouping when
 * every group is well formed ("1,000", "12.345.678") and never after a leading
 * zero, so "0.125" is rejected instead of being read as 125 DA.
 *
 * Returns null for empty input and NaN for anything invalid.
 */
export function parseMoneyInput(input: string): number | null {
    let value = input.trim().replace(/\s*DA\s*$/i, '');
    value = value.replace(/[\u00A0\u202F\u2009 '_]/g, '');

    if (value === '') {
        return null;
    }

    const negative = value.startsWith('-');
    value = value.replace(/^[+-]+/, '');

    if (value === '' || /[^0-9.,]/.test(value)) {
        return Number.NaN;
    }

    const lastSeparator = Math.max(
        value.lastIndexOf(','),
        value.lastIndexOf('.'),
    );

    let whole: string;
    let fraction = '';

    if (lastSeparator === -1) {
        whole = value;
    } else {
        const after = value.slice(lastSeparator + 1);

        if (/^\d{1,2}$/.test(after)) {
            const integer = value.slice(0, lastSeparator);
            const grouping = value[lastSeparator] === ',' ? '.' : ',';

            if (integer === '' || /^\d+$/.test(integer)) {
                whole = integer === '' ? '0' : integer;
            } else if (isGroupedInteger(integer, grouping)) {
                whole = integer.split(grouping).join('');
            } else {
                return Number.NaN;
            }

            fraction = after.padEnd(2, '0');
        } else {
            const grouping = [',', '.'].find((mark) =>
                isGroupedInteger(value, mark),
            );

            if (grouping === undefined) {
                return Number.NaN;
            }

            whole = value.split(grouping).join('');
        }
    }

    if (whole.replace(/^0+/, '').length > 13) {
        return Number.NaN;
    }

    const cents = Number(whole) * 100 + Number(fraction || '0');

    return negative && cents !== 0 ? -cents : cents;
}
