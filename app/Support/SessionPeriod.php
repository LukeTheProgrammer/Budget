<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class SessionPeriod
{
    /**
     * The selectable preset period types (custom is handled separately).
     *
     * @var list<string>
     */
    public const PRESETS = ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'last_12_months', 'ytd'];

    public function __construct(
        public readonly string $type,
        public readonly ?Carbon $start = null,
        public readonly ?Carbon $end = null,
    ) {}

    /**
     * The default period applied when the user has made no selection.
     */
    public static function default(): self
    {
        return new self('this_month');
    }

    /**
     * Build a period from the raw session payload, falling back to the default
     * when the stored value is missing or malformed.
     *
     * @param  array{type?: string, start?: string, end?: string}|null  $payload
     */
    public static function fromSession(?array $payload): self
    {
        $type = $payload['type'] ?? null;

        if (in_array($type, self::PRESETS, true)) {
            return new self($type);
        }

        if ($type === 'custom') {
            $start = isset($payload['start']) ? Carbon::parse($payload['start']) : null;
            $end = isset($payload['end']) ? Carbon::parse($payload['end']) : null;

            if ($start !== null && $end !== null) {
                return new self('custom', $start, $end);
            }
        }

        return self::default();
    }

    /**
     * Resolve the inclusive [start, end] date window for this period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(): array
    {
        $now = Carbon::now();

        if ($this->type === 'custom' && $this->start !== null && $this->end !== null) {
            return [
                $this->start->toMutable()->startOfDay(),
                $this->end->toMutable()->endOfDay(),
            ];
        }

        return match ($this->type) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_3_months' => [
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'last_6_months' => [
                $now->copy()->subMonthsNoOverflow(5)->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'last_12_months' => [
                $now->copy()->subMonthsNoOverflow(11)->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'ytd' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfMonth(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * The number of calendar months spanned by the resolved window, used to
     * scale recurring monthly budgets to the active period (floored at 1).
     */
    public function months(): int
    {
        [$start, $end] = $this->window();

        return (int) $start->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth()) + 1;
    }

    /**
     * Build a human-readable label for the resolved period window.
     */
    public function label(): string
    {
        [$start, $end] = $this->window();

        if ($this->type === 'custom') {
            return "{$start->format('M j, Y')} – {$end->format('M j, Y')}";
        }

        if (in_array($this->type, ['last_3_months', 'last_6_months', 'last_12_months', 'ytd'], true)) {
            return $start->isSameYear($end)
                ? "{$start->format('M')} – {$end->format('M Y')}"
                : "{$start->format('M Y')} – {$end->format('M Y')}";
        }

        return $start->format('F Y');
    }

    /**
     * The shape shared with the frontend via the global Inertia prop.
     *
     * @return array{type: string, start: string|null, end: string|null, label: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'start' => $this->start?->toDateString(),
            'end' => $this->end?->toDateString(),
            'label' => $this->label(),
        ];
    }
}
