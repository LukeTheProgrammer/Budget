/** Fallback palette for categories without an assigned color. */
const PALETTE = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

/**
 * Resolve a category's swatch color, falling back to a stable palette entry
 * keyed by position when the category has no color of its own.
 */
export function swatchColor(color: string | null, index: number): string {
    return color ?? PALETTE[index % PALETTE.length];
}
