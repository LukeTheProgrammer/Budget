# Phase 0 Research: Transactions Table with Filters

No open `NEEDS CLARIFICATION` items remained after `/speckit-clarify`. This document records the design decisions for the resolved approach.

## Decision 1 — Server-side filtering & pagination

**Decision**: Filter and paginate on the server using a single Eloquent query scope on `Transaction`, returning a `LengthAwarePaginator` to Inertia via `->withQueryString()`.

**Rationale**: Matches the existing `DashboardController`/`MerchantController` pattern (query scopes on `Transaction`, mapped to display rows). Server-side pagination keeps the payload small and meets SC-004 (<1s for 10k transactions) by relying on the indexed `posted_at` column and `LIMIT/OFFSET`. Avoids shipping the full dataset to the client.

**Alternatives considered**: Client-side filtering of a fully-loaded dataset — rejected: does not scale to 10k rows and contradicts Principle V (simplicity) and SC-004.

## Decision 2 — URL query parameters as the single source of truth

**Decision**: Filters live in the URL query string (`start`, `end`, `merchant_id`, `category_id`, `min_amount`, `max_amount`, `page`). On load, the controller reads them from the validated request; the React page hydrates its controls from props derived from those same params. On change, the page issues an Inertia GET visit to `transactions.index` with the updated params, using `preserveState` and `preserveScroll` and `replace` to update the URL without a full navigation.

**Rationale**: Directly satisfies FR-011/FR-012 and User Story 3 (shareable, reloadable, bookmarkable views) with a single source of truth, eliminating client/URL drift. Inertia partial visits + `withQueryString()` is the idiomatic v3 approach.

**Alternatives considered**: Holding filter state only in React state and syncing to the URL manually with `history.pushState` — rejected: duplicates state, risks drift, and re-implements what an Inertia visit already does.

## Decision 3 — Validation via FormRequest, invalid params ignored

**Decision**: A `TransactionFilterRequest` validates each query parameter as `nullable` with appropriate types (`date`, `integer`/`exists`, `numeric`/`decimal`). Invalid or unknown params fail validation softly — the controller treats a failed/absent value as "filter not applied" rather than erroring, satisfying FR-013.

**Rationale**: Keeps malformed URLs from throwing (User Story 3, scenario 4) while still constraining valid input. `exists` rules are scoped to the user's own merchants/categories so crafted IDs for other users yield no filter match (SC-005, FR-002).

**Alternatives considered**: Hard 422 on bad params — rejected: a shared/edited URL with one stale value should still render the page, not error.

## Decision 4 — Per-user data isolation

**Decision**: The filter scope joins `accounts` and constrains `accounts.user_id = $userId` (as `spendingByCategory`/`spendingSummary` already do). Merchant/category filter values are validated with `exists` rules scoped to the user, and the filter-option lists are built only from the user's own merchants/categories.

**Rationale**: Enforces SC-005/FR-002 at the query level so no crafted parameter can leak another user's data.

**Alternatives considered**: Filtering ownership only in PHP after fetching — rejected: weaker guarantee and wasteful.

## Decision 5 — Amount units

**Decision**: Users enter amounts in major units (dollars). The request converts `min_amount`/`max_amount` to cents (`round($value * 100)`) before comparing against `amount_cents`.

**Rationale**: Matches the Assumptions in the spec and the storage model (`amount_cents` integer). Display already uses `Intl.NumberFormat(... value/100)` in sibling pages.

**Alternatives considered**: Filtering on raw cents in the URL — rejected: unfriendly to users editing the URL and inconsistent with the displayed values.

## Decision 6 — Table rendering & default page size

**Decision**: Render with a new shadcn `table` primitive (`components/ui/table.tsx`), default page size **50** rows, ordered `posted_at desc, id desc`. Empty state shown when no rows match.

**Rationale**: shadcn `table` is a first-party generator (no new dependency, Principle III/V compliant) and there is no existing table primitive. 50 balances density and payload. The `id desc` tiebreak mirrors `recentSpending`/`largestSpending`.

**Alternatives considered**: Reusing ad-hoc markup like `merchants/index.tsx` — acceptable, but a shared `table` primitive is cleaner and reusable for future pages.
