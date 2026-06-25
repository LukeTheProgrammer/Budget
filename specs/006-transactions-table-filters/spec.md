# Feature Specification: Transactions Table with Filters

**Feature Branch**: `006-transactions-table-filters`

**Created**: 2026-06-03

**Status**: Draft

**Input**: User description: "Create a transactions pages that will show a table of transactions. The table should be filterable by date range, merchant, category, and amount. The filters should load from query parameters on page laod and update query parameters when changed."

## Clarifications

### Session 2026-06-03

- Q: Should the merchant and category filters allow selecting one value or multiple? → A: Single value each — one merchant and/or one category at a time.
- Q: With no filter query parameters present, what is the default result set on load? → A: All transactions, most recent first, paginated (no implicit date filter).
- Q: When the user changes a filter while paginated, what happens to the current page? → A: Reset to the first page on any filter change.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse all transactions in a table (Priority: P1)

As a user, I want to open a transactions page and see all of my transactions in a clear table so that I can review my spending history at a glance.

**Why this priority**: The table is the foundation of the feature. Without it there is nothing to filter. It delivers immediate value on its own by giving the user visibility into their transactions.

**Independent Test**: Navigate to the transactions page with no filters applied and confirm a table of the user's transactions renders with date, merchant, category, description, and amount, ordered most recent first.

**Acceptance Scenarios**:

1. **Given** a user with recorded transactions, **When** they open the transactions page, **Then** their transactions are displayed in a table showing date, merchant, category, description, and amount.
2. **Given** a user with no transactions, **When** they open the transactions page, **Then** an empty state message is shown instead of an empty table.
3. **Given** a user with more transactions than fit on one screen, **When** they view the page, **Then** transactions are paginated so the page remains responsive.

---

### User Story 2 - Filter transactions by date range, merchant, category, and amount (Priority: P1)

As a user, I want to narrow the table down by date range, merchant, category, and amount so that I can find specific transactions quickly.

**Why this priority**: Filtering is the core purpose stated in the request. A user reviewing finances needs to isolate subsets of transactions to be useful.

**Independent Test**: Apply each filter individually and in combination, and confirm the table only shows transactions matching every active filter.

**Acceptance Scenarios**:

1. **Given** the transactions page, **When** the user sets a start and/or end date, **Then** only transactions posted within that range are shown.
2. **Given** the transactions page, **When** the user selects a merchant, **Then** only transactions for that merchant are shown.
3. **Given** the transactions page, **When** the user selects a category, **Then** only transactions whose merchant belongs to that category are shown.
4. **Given** the transactions page, **When** the user sets a minimum and/or maximum amount, **Then** only transactions within that amount range are shown.
5. **Given** multiple active filters, **When** the table updates, **Then** only transactions matching all active filters are shown.
6. **Given** active filters, **When** the user clears them, **Then** the full transaction list returns.

---

### User Story 3 - Shareable and persistent filters via the URL (Priority: P2)

As a user, I want the page to read its filters from the URL on load and write them back to the URL when I change them, so that I can bookmark, share, or reload a filtered view without losing it.

**Why this priority**: This makes the filtered views durable and shareable, a meaningful usability gain, but the filtering itself (P1) delivers value even without URL persistence.

**Independent Test**: Open the page with filter query parameters present and confirm the table loads pre-filtered; then change a filter and confirm the URL updates to reflect the new state; then reload and confirm the state is preserved.

**Acceptance Scenarios**:

1. **Given** a URL containing filter query parameters, **When** the page loads, **Then** the filter controls and the table reflect those parameters.
2. **Given** the page is open, **When** the user changes any filter, **Then** the URL query parameters update to match the active filters.
3. **Given** a filtered view, **When** the user reloads the page or shares the URL, **Then** the same filtered results are produced.
4. **Given** a URL with an invalid or malformed filter value, **When** the page loads, **Then** the invalid value is ignored and the page loads without error.

---

### Edge Cases

- What happens when the start date is later than the end date? The system shows no results (an empty, consistent range) rather than erroring.
- What happens when the minimum amount is greater than the maximum amount? The system shows no results rather than erroring.
- How does the system handle a transaction with no associated merchant or category? It still appears in the unfiltered table (shown as having no merchant/category) and is excluded only when a merchant or category filter is active.
- How does the system handle filter values referencing a merchant or category that does not belong to the user? No transactions are returned for that value, and no other user's data is exposed.
- What happens when filters combine to match zero transactions? An empty state is shown indicating no transactions match the current filters, with the filters still visible and editable.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display the authenticated user's transactions in a table, including transaction date, merchant, category, description, and amount.
- **FR-002**: System MUST only show transactions belonging to the authenticated user and never expose another user's transactions, merchants, or categories.
- **FR-003**: System MUST order transactions by most recent transaction date first by default, and when no filters are present MUST show all of the user's transactions (no implicit date filter).
- **FR-004**: System MUST paginate the transactions list so the page remains responsive with large transaction volumes.
- **FR-005**: Users MUST be able to filter transactions by a date range (start date and/or end date), inclusive of the boundary dates.
- **FR-006**: Users MUST be able to filter transactions by a single selected merchant.
- **FR-007**: Users MUST be able to filter transactions by a single selected category, matching transactions whose merchant belongs to the selected category.
- **FR-008**: Users MUST be able to filter transactions by an amount range (minimum and/or maximum), inclusive of the boundary amounts.
- **FR-009**: System MUST combine all active filters so results match every active filter simultaneously.
- **FR-010**: Users MUST be able to clear individual filters or all filters to return to the full list.
- **FR-010a**: System MUST reset pagination to the first page whenever any filter value changes.
- **FR-011**: System MUST read filter values from the page's URL query parameters on initial load and apply them to both the filter controls and the results.
- **FR-012**: System MUST update the URL query parameters to reflect the active filters whenever a filter changes, without a full page navigation.
- **FR-013**: System MUST ignore invalid, unknown, or malformed filter query parameters and load the page without error.
- **FR-014**: System MUST display an empty state when no transactions match the active filters (or when the user has no transactions), while keeping filter controls available.
- **FR-015**: System MUST present amounts in a clearly readable monetary format with their currency.

### Key Entities *(include if feature involves data)*

- **Transaction**: A single financial transaction belonging to one of the user's accounts. Key attributes: posted date, amount, currency, description. Related to a merchant (optional) and, through that merchant, to a category.
- **Merchant**: The party a transaction was made with. Used as a filter option and displayed in the table. Belongs to a category.
- **Category**: A grouping of merchants (e.g. Groceries, Dining). Used as a filter option and displayed in the table.
- **Account**: The user's account that owns transactions; establishes ownership for access control.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can locate a specific past transaction using filters in under 30 seconds.
- **SC-002**: Filtered results returned by any combination of filters contain only transactions matching every active filter, with zero non-matching rows.
- **SC-003**: 100% of applied filters are reflected in the URL, and reloading or sharing that URL reproduces the identical result set.
- **SC-004**: The transactions table renders and updates after a filter change within 1 second for a user with up to 10,000 transactions.
- **SC-005**: No user can view another user's transactions through any filter combination or crafted URL parameter.

## Assumptions

- The transactions page is available only to authenticated users and shows only that user's data.
- The merchant and category filters present options scoped to the authenticated user's own merchants and categories.
- Date filtering is based on the transaction's posted date.
- Amounts are filtered using the displayed monetary value; users enter amounts in major currency units (e.g. dollars), not minor units (cents).
- A reasonable default page size is used for pagination (e.g. 25–50 rows per page); exact size is an implementation detail.
- This feature reuses the existing Transaction, Merchant, Category, and Account data already present in the application.
- Mobile-specific layout optimizations beyond a responsive table are out of scope for the initial version.
- Sorting by columns other than the default date order is out of scope for the initial version.
