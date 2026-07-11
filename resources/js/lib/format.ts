/**
 * Format an integer cent amount as a localized currency string.
 *
 * Amounts are stored as integer cents; divide by 100 for display. Pass
 * `wholeDollars` to drop the fractional part and render rounded whole units
 * (e.g. "$1,234" instead of "$1,234.56").
 */
export function formatMoney(amountCents: number, currency: string, wholeDollars = false): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        ...(wholeDollars ? { minimumFractionDigits: 0, maximumFractionDigits: 0 } : {}),
    }).format(amountCents / 100);
}

/**
 * Format a signed integer cent amount as a currency string with an explicit
 * leading sign (e.g. "+$36", "−$54"). Zero is rendered without a sign.
 */
export function formatSignedMoney(amountCents: number, currency: string): string {
    const formatted = formatMoney(Math.abs(amountCents), currency);

    if (amountCents > 0) {
        return `+${formatted}`;
    }

    if (amountCents < 0) {
        return `−${formatted}`;
    }

    return formatted;
}

/**
 * Format a signed percentage change for display (e.g. "+12.3%", "-4.0%").
 *
 * Returns null when there is no comparable value, so callers can render a
 * neutral state instead of a misleading figure.
 */
export function formatChangePercent(percent: number | null): string | null {
    if (percent === null) {
        return null;
    }

    const sign = percent > 0 ? '+' : '';

    return `${sign}${percent.toFixed(1)}%`;
}
