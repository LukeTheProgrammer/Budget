# Data Model: Session Period Selector

No database schema changes. The feature introduces one in-memory/session value object;
all persistence is via the Laravel session.

## Value Object: `SessionPeriod` (`App\Support\SessionPeriod`)

Encapsulates the selected time frame and resolves it to a concrete date window.

| Field   | Type                                                        | Notes |
|---------|------------------------------------------------------------|-------|
| `type`  | enum: `this_month` \| `last_month` \| `last_3_months` \| `custom` | The selected option |
| `start` | `?CarbonImmutable`                                          | Required only when `type = custom`; null otherwise |
| `end`   | `?CarbonImmutable`                                          | Required only when `type = custom`; null otherwise |

### Behavior

- `window(): array{0: Carbon, 1: Carbon}` — inclusive `[start, end]`:
  - `this_month` → start/end of current month
  - `last_month` → start/end of previous month
  - `last_3_months` → start of month two months ago … end of current month
  - `custom` → `start->startOfDay()` … `end->endOfDay()`
- `label(): string` — human-readable window label (reuses existing Dashboard label
  formatting; custom renders `M j, Y – M j, Y`).
- `toArray(): array` — shape shared with the frontend (see contract): `{ type, start, end, label }`.
- `default(): self` — `this_month` (FR-006).

### Validation Rules (enforced by `SessionPeriodRequest`)

- `type` MUST be one of the four allowed values; otherwise reject (UI) / fall back to default.
- When `type = custom`: `start` and `end` are required, valid dates, and `end >= start`
  (FR-007). Incomplete or inverted ranges are rejected with a message (SC-005).
- Dates interpreted as local calendar dates, inclusive of both endpoints (FR-011).

## Session State

| Session key       | Stored shape                                  | Lifetime |
|-------------------|-----------------------------------------------|----------|
| `session_period`  | `{ type: string, start?: string(Y-m-d), end?: string(Y-m-d) }` | Session (resets on new session → default) |

## Shared Inertia Prop

`HandleInertiaRequests::share()` adds `period` (the `SessionPeriod::toArray()` shape) so
every authenticated page receives the active window and label without per-page wiring.
