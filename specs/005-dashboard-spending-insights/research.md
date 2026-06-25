# Phase 0 Research: Dashboard Spending Insights

## Decision 1: Charting library

- **Decision**: Add `recharts` and the shadcn/ui `chart` primitive (`resources/js/components/ui/chart.tsx`).
- **Rationale**: User explicitly approved (constitution requires approval for new dependencies). recharts is the canonical dependency behind shadcn/ui charts, keeping the implementation framework-idiomatic (Principle III) and giving polished, responsive, themeable charts with minimal code.
- **Alternatives considered**: Hand-built SVG + Tailwind charts (zero new dependency) — rejected by user in favor of recharts polish and consistency with shadcn patterns.

## Decision 2: Spending sign convention & refund handling

- **Decision**: Spending = transactions with **positive** `amount_cents`. Refunds/credits are stored as **negative** `amount_cents` (per `TransactionFactory::refund()`) and are excluded from every spending figure (`where amount_cents > 0`).
- **Rationale**: Matches the existing data convention and the spec clarification (gross spend, refunds excluded — FR-001a). Guarantees SC-003 reconciliation.
- **Alternatives considered**: Netting refunds against spend — rejected per clarification Q2 (Option B, gross spend).

## Decision 3: Period selection mechanism

- **Decision**: Period is a query-string parameter (`?period=this_month|last_month|last_3_months`, default `this_month`) read by `DashboardController`. The page selector navigates via an Inertia visit (Wayfinder `dashboard()` helper) preserving scroll/state, so all widgets recompute server-side in one request.
- **Rationale**: Server-side aggregation keeps raw transactions off the wire (SC-002), gives a single source of truth so no widget shows stale data (SC-005), and is idiomatic Inertia. Avoids client-side date math duplication.
- **Alternatives considered**: Client-side filtering of a full transaction payload — rejected (defeats SC-002/SC-005 and ships unnecessary data). Deferred props per-widget — unnecessary complexity for a single fast query set (YAGNI, Principle V).

## Decision 4: "Previous period" comparison

- **Decision**: The comparison window is the immediately preceding window of equal length (this month → previous month; last 3 months → the 3 months before that). Percentage change = `(current - previous) / previous * 100`. When `previous == 0` (no comparable prior spend), return a neutral state (no percentage), satisfying FR-012.
- **Rationale**: Equal-length windows make the change figure meaningful; neutral state avoids divide-by-zero and misleading infinities (edge case).
- **Alternatives considered**: Year-over-year comparison — out of scope for this iteration.

## Decision 5: Trend window

- **Decision**: Fixed trailing 12 calendar months of monthly spending totals, independent of the selected period, with zero-filled gaps (FR-006, clarification Q3).
- **Rationale**: Matches SC-002 horizon and reveals full-year seasonality. Zero-filling keeps the timeline continuous.
- **Alternatives considered**: 6 months / period-relative windows — rejected per clarification Q3 (Option B).

## Decision 6: Aggregation approach (queries)

- **Decision**: Add `#[Scope]` methods on `Transaction` mirroring the existing `spendingByCategory` pattern: period summary (sum + count), monthly trend (group by year-month), recent (latest 10), largest (top 10 by amount). All join `accounts` and filter `accounts.user_id`, `amount_cents > 0`, and the date window. Category rolls up through `merchants.category_id` with NULL → "Uncategorized" (FR-005).
- **Rationale**: Reuses the proven scope on `Transaction`, keeps SQL aggregation in the database (SC-002), and centralizes user scoping (FR-010).
- **Alternatives considered**: A dedicated service/repository class — rejected as over-abstraction for one controller (Principle V, mirrors Merchants module which queries models directly).

## Decision 7: Category "Other" grouping in chart

- **Decision**: The category **chart** shows the top N categories (N = 8) by spend and consolidates the remainder into an "Other" slice; the category **table** lists all categories (FR-013). Grouping is done in the controller/page mapping, not SQL.
- **Rationale**: Keeps the chart readable while preserving full detail in the table.
- **Alternatives considered**: Always show all in chart — rejected (unreadable with many categories).

## Open questions

None. All spec clarifications resolved; the single dependency decision is approved.
