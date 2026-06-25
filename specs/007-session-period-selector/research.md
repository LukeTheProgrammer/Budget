# Research: Session Period Selector

## R1. Where to store the session-wide period

**Decision**: Store the selection in the Laravel **session** (server-side), updated via a
dedicated POST route, and expose the resolved value as a shared Inertia prop in
`HandleInertiaRequests::share()`.

**Rationale**: "Applies on every page for the session and resets in a new session" maps
exactly onto session storage. Sharing via Inertia means every page receives `period`
without each controller re-reading query strings. A single round-trip (POST → redirect
back) re-renders the current page with the new window, satisfying FR-005/FR-009 and the
session-scope assumption.

**Alternatives considered**:
- *Query string per page* (current Dashboard approach): does not persist across
  navigation; would require threading `?period=` through every link. Rejected.
- *Client-side store (localStorage/context only)*: would not scope server-side data
  queries without also sending the value on every request; reinvents what the session
  provides. Rejected.
- *Persisted user preference (DB column)*: contradicts the session-reset assumption and
  adds a migration; out of scope for v1. Rejected.

## R2. Resolving preset + custom range to a date window

**Decision**: Extract the period→`[start, end]` resolution (currently private methods in
`DashboardController`: `resolvePeriod`, `periodWindow`, `periodLabel`) into a small value
object `App\Support\SessionPeriod` with a type (`this_month | last_month | last_3_months
| custom`), optional custom `start`/`end`, and methods `window(): array{Carbon, Carbon}`
and `label(): string`. The middleware builds it from the session; controllers consume it.

**Rationale**: Centralizes the existing logic so Dashboard and future pages share one
source of truth (FR-004, FR-010). Keeps the controller thin and idiomatic.

**Alternatives considered**:
- *Leave logic in DashboardController and duplicate per page*: violates DRY and
  Principle V. Rejected.

## R3. Custom-range input without new dependencies

**Decision**: Build the custom range with the existing shadcn/ui `popover` + native
`<input type="date">` (the project's `input.tsx`) for start and end. The selector itself
uses the existing shadcn/ui `select` primitive.

**Rationale**: `select`, `popover`, and `input` already exist in
`resources/js/components/ui/`. No calendar/date-picker dependency needs to be added,
honoring the no-new-dependencies constraint (Constitution Technology Constraints).

**Alternatives considered**:
- *Add a calendar/date-picker library*: requires dependency approval; over-engineered
  for two date fields. Rejected.

## R4. Validation of the period selection

**Decision**: A `SessionPeriodRequest` form request validates `type` against the allowed
set; when `type=custom`, both `start` and `end` are required dates with `end >= start`
(FR-007, FR-011). Invalid presets fall back to the default; invalid custom ranges are
rejected with a message surfaced in the UI.

**Rationale**: Mirrors the existing `TransactionFilterRequest` pattern (form request with
`rules()` and sanitization), keeping the codebase consistent.

## R5. Date interpretation / inclusivity

**Decision**: Custom ranges are inclusive of both endpoints and use local calendar dates
(`Carbon::parse($start)->startOfDay()` … `->endOfDay()`), consistent with the existing
`periodWindow` semantics and the spec assumptions (FR-011).

**Rationale**: Matches current Dashboard window behavior and user expectations for date
pickers.
