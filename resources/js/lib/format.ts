const DISPLAY_TIMEZONE = "Africa/Algiers";

const moneyFormatter = new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat("en-US", {
    maximumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    timeZone: DISPLAY_TIMEZONE,
});

const dateTimeFormatter = new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
    timeZone: DISPLAY_TIMEZONE,
});

/**
 * Format integer centimes as English money text, e.g. 1000050 => "10,000.50 DA".
 */
export function formatMoney(cents: number, symbol = "DA"): string {
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
        typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)
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
    const parts = new Intl.DateTimeFormat("en-CA", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        timeZone: DISPLAY_TIMEZONE,
    }).formatToParts(value);

    const get = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? "";

    return `${get("year")}-${get("month")}-${get("day")}`;
}

export { DISPLAY_TIMEZONE };
