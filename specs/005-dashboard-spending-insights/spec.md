# Feature Specification: Dashboard Spending Insights

**Feature Branch**: `005-dashboard-spending-insights`

**Created**: 2026-06-02

**Status**: Draft

**Input**: User description: "Populate the content on the Dashboard page with helpful charts and tables that will assist users in understanding their spending behavior"

## Clarifications

### Session 2026-06-02

- Q: How should the dashboard handle spending across differing currencies? → A: Assume a single currency for this iteration; multi-currency aggregation/conversion is out of scope.
- Q: What counts as "spending" versus income/refunds? → A: Spending = debit/outflow transactions only; refunds and credits (inflows) are excluded entirely (gross spend).
- Q: How many periods should the spending trend chart cover? → A: Last 12 months (monthly totals).
- Q: How many rows should the recent- and largest-transaction tables show? → A: 10 rows each.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See spending at a glance for a period (Priority: P1)

A user opens the Dashboard and immediately sees how much they have spent during the current period (e.g. this month), how that compares to the previous period, and the headline numbers that summarize their financial activity. This gives them an instant sense of whether their spending is up or down without digging through individual transactions.

**Why this priority**: This is the core value of a dashboard — answering "how am I doing?" in seconds. Without it the page has no reason to exist. It is the smallest standalone slice that delivers immediate value.

**Independent Test**: Load the Dashboard with a set of transactions for the signed-in user and confirm the summary figures (total spent this period, change vs. previous period, transaction count) display correctly and recalculate when the selected period changes.

**Acceptance Scenarios**:

1. **Given** a user with transactions in the current month, **When** they open the Dashboard, **Then** they see the total amount spent this month, the number of transactions, and the percentage change compared with the previous month.
2. **Given** a user with no transactions in the selected period, **When** they open the Dashboard, **Then** they see a clear empty state rather than blank or error content.
3. **Given** a user viewing the Dashboard, **When** they change the selected period (e.g. last month, last 3 months), **Then** all summary figures update to reflect the chosen period.

---

### User Story 2 - Understand spending by category (Priority: P2)

A user wants to know where their money goes. The Dashboard shows a breakdown of spending by category (e.g. Groceries, Dining, Transport) as a chart and an accompanying ranked table, so the user can identify their largest spending areas and spot anything unexpected.

**Why this priority**: Category breakdown is the single most requested insight in personal budgeting and turns raw totals into actionable understanding. It builds directly on the period summary from P1.

**Independent Test**: Load the Dashboard for a user whose transactions span multiple categories and confirm the category chart and table show each category's total and share of overall spending, sorted from largest to smallest, with uncategorized spending represented.

**Acceptance Scenarios**:

1. **Given** a user with transactions across several categories, **When** they view the Dashboard, **Then** they see a chart and a table ranking categories by total spend for the selected period.
2. **Given** transactions with no assigned category, **When** the breakdown is shown, **Then** that spend appears grouped as "Uncategorized".
3. **Given** the category breakdown is displayed, **When** the user reads a category row, **Then** they see both the amount and its percentage of total spending for the period.

---

### User Story 3 - See spending trend over time (Priority: P2)

A user wants to see whether their spending is trending up or down. The Dashboard shows a time-series chart of spending across recent periods (e.g. monthly totals over the last several months) so the user can recognize patterns and seasonality.

**Why this priority**: Trend context distinguishes a one-off high month from a worsening habit. It complements the category breakdown and reinforces the headline change figure from P1.

**Independent Test**: Load the Dashboard for a user with transactions spanning multiple months and confirm the trend chart plots one point/bar per period with correct totals in chronological order.

**Acceptance Scenarios**:

1. **Given** a user with transactions over several months, **When** they view the Dashboard, **Then** they see a chart of total spending per period in chronological order.
2. **Given** a period within the range has no transactions, **When** the trend is shown, **Then** that period displays as zero rather than being omitted, keeping the timeline continuous.

---

### User Story 4 - Review recent and largest transactions (Priority: P3)

A user wants quick visibility of their most recent activity and their biggest individual purchases without leaving the Dashboard. A compact table lists recent transactions (merchant, category, date, amount) and another highlights the largest transactions for the period.

**Why this priority**: Tables ground the aggregate insights in concrete transactions and let the user sanity-check the numbers, but the headline summary and breakdowns deliver value first.

**Independent Test**: Load the Dashboard for a user with transactions and confirm the recent-transactions table lists the latest entries in date order and the largest-transactions table lists the highest-amount entries for the period.

**Acceptance Scenarios**:

1. **Given** a user with recent transactions, **When** they view the Dashboard, **Then** they see a table of the most recent transactions showing merchant, category, date, and amount.
2. **Given** a user with transactions in the period, **When** they view the largest-transactions table, **Then** entries are ordered from highest to lowest amount.

---

### Edge Cases

- **No data at all**: A brand-new user with no accounts or transactions sees a friendly empty/onboarding state across every widget, not errors or zero-filled noise.
- **Single transaction**: Charts and comparisons still render sensibly (e.g. trend with one point, no division-by-zero in percentage change).
- **No prior period**: When there is no previous period to compare against, the change indicator shows a neutral state rather than a misleading percentage.
- **Multiple currencies**: Out of scope for this iteration — the dashboard assumes the user's transactions share a single currency.
- **Large category counts**: When spending spans many categories, the chart groups the smallest into an "Other" bucket so it stays readable while the table can still show all.
- **Income / positive amounts**: The dashboard focuses on spending; refunds and credits (inflows) are excluded from all spending totals rather than netted against them.
- **Data scoping**: A user only ever sees their own accounts' transactions.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Dashboard MUST display, for the selected period, the total amount spent, the number of transactions, and the change relative to the immediately preceding period of equal length.
- **FR-001a**: All spending metrics MUST be computed from debit/outflow transactions only; refunds and credits (inflows) MUST be excluded from every spending figure, breakdown, trend, and transaction table.
- **FR-002**: The Dashboard MUST allow the user to select the reporting period from a small set of presets (at minimum: this month, last month, last 3 months).
- **FR-003**: All period-scoped Dashboard widgets (summary, category breakdown, largest transactions) MUST recalculate to reflect the currently selected period. The spending-trend widget is intentionally a fixed trailing-12-month window (see FR-006) and does not change with the selected period.
- **FR-004**: The Dashboard MUST present spending broken down by category for the selected period as both a chart and a ranked table, showing each category's total amount and its percentage share of total spending.
- **FR-005**: Spending with no assigned category MUST be represented as "Uncategorized" in the breakdown.
- **FR-006**: The Dashboard MUST present a time-series view of total monthly spending across the last 12 months in chronological order, including months with zero spending.
- **FR-007**: The Dashboard MUST present a table of the 10 most recent transactions showing merchant name, category, date, and amount.
- **FR-008**: The Dashboard MUST present a table of the 10 largest transactions for the selected period, ordered from highest to lowest amount.
- **FR-009**: Every widget MUST display an appropriate empty state when no data exists for the selected period.
- **FR-010**: The Dashboard MUST only display data belonging to the authenticated user's own accounts.
- **FR-011**: Monetary values MUST be displayed formatted with their currency. The dashboard assumes a single currency per user for this iteration; aggregating or converting across differing currencies is out of scope.
- **FR-012**: The change-vs-previous-period indicator MUST handle the absence of a comparable prior period without showing a misleading value.
- **FR-013**: When the number of categories exceeds a readable limit (top 8 by spend), the category chart MUST consolidate the remaining smallest categories into a single "Other" group while the category table MAY list all categories.

### Key Entities *(include if feature involves data)*

- **Transaction**: An individual spending record belonging to an account, with an amount, currency, date, optional merchant, and optional category (via merchant). The atomic unit aggregated by every widget.
- **Category**: A user-defined grouping of spending (e.g. Groceries). Used to break down and rank spending; transactions without one are "Uncategorized".
- **Merchant**: The party a transaction was paid to; surfaced in transaction tables and carries the category association.
- **Account**: A user's financial account that owns transactions; scopes all dashboard data to the signed-in user and carries the currency.
- **Reporting Period**: The user-selected time window (and its preceding window) over which all metrics are computed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can determine their total spending and category breakdown for the current period within 5 seconds of the Dashboard loading, without any further navigation.
- **SC-002**: The Dashboard loads and renders all widgets within 2 seconds for a user with up to 12 months of transaction history.
- **SC-003**: 100% of displayed spending figures reconcile exactly with the sum of the underlying transactions for the selected period.
- **SC-004**: Every widget renders a meaningful state (data or empty) with zero error conditions for users at any data volume, including users with no transactions.
- **SC-005**: Changing the selected period updates every widget consistently in a single interaction, with no widget showing stale data from a previous period.

## Assumptions

- The Dashboard is for the authenticated user only; there is no shared or multi-user view.
- "Spending" refers to transaction amounts as already stored; the existing `spendingByCategory` aggregation pattern and the established amount/currency conventions are reused.
- Transactions roll up to a category through their merchant, consistent with the current data model (a null category is "Uncategorized").
- Monthly is the default and primary period granularity; preset ranges are sufficient and a custom date-range picker is out of scope for this iteration.
- Budgets/targets and forecasting are out of scope for this iteration; the focus is on understanding past and present spending behavior.
- The visual presentation uses the project's existing UI component library and styling conventions.
