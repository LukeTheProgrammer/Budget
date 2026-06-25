# Feature Specification: Transaction Data & Merchant-Category Spending Analysis

**Feature Branch**: `002-transaction-data-and`

**Created**: 2026-06-01

**Status**: Draft

**Input**: User description: "Analyze credit card purchases by categorizing them by merchant and tracking spending on those categories over time. Database schema for transaction data and all related tables."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record credit card transactions (Priority: P1)

A user adds credit card purchases (manually or via import) so that every purchase is
stored with its amount, date, and the merchant where it occurred.

**Why this priority**: Without persisted transactions there is nothing to categorize or
analyze. This is the foundational data slice.

**Independent Test**: Add several transactions tied to merchants and confirm they are
listed back with correct amounts, dates, and merchant names.

**Acceptance Scenarios**:

1. **Given** a credit card account exists, **When** a purchase is recorded with amount,
   date, and merchant, **Then** the transaction is persisted and associated with that
   account and merchant.
2. **Given** an imported batch of purchases, **When** the same purchase appears twice,
   **Then** duplicates are detectable and not double-counted.

---

### User Story 2 - Categorize merchants (Priority: P2)

A user assigns each merchant to a spending category (e.g., Groceries, Dining, Fuel) so
that transactions inherit a category through their merchant.

**Why this priority**: Categorization is what turns raw transactions into meaningful
spending groups; it depends on transactions existing.

**Independent Test**: Assign a merchant to a category and confirm that all of that
merchant's transactions roll up under the category.

**Acceptance Scenarios**:

1. **Given** a merchant with transactions, **When** it is assigned a category, **Then**
   its transactions are reported under that category.
2. **Given** an uncategorized merchant, **When** spending is reported, **Then** its
   transactions appear under an "Uncategorized" grouping.

---

### User Story 3 - Track category spending over time (Priority: P3)

A user views total spending per category across time periods (month, quarter, year) to
understand trends.

**Why this priority**: This is the primary analytical payoff, built on stories 1 and 2.

**Independent Test**: Record transactions across several months, then view per-category
monthly totals and confirm they match the underlying transactions.

**Acceptance Scenarios**:

1. **Given** categorized transactions spanning multiple months, **When** the user views
   a category over time, **Then** correct period totals are shown.

---

### Edge Cases

- A merchant name appears with slight variations across purchases (normalization needed).
- A transaction is a refund/credit (negative amount) and must reduce category spend.
- A merchant is reassigned to a different category — historical reporting must reflect
  the merchant's current category mapping consistently.
- A transaction has no resolvable merchant (must still be storable and reportable).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST persist credit card transactions with amount, currency,
  transaction date, and a reference to the merchant and the card/account.
- **FR-002**: System MUST store merchants as first-class records that can be referenced
  by many transactions.
- **FR-003**: System MUST allow each merchant to be assigned to exactly one category.
- **FR-004**: System MUST allow categories to be created and named by the user.
- **FR-005**: System MUST support querying total spending per category over arbitrary
  date ranges.
- **FR-006**: System MUST distinguish purchases from refunds/credits (signed amounts).
- **FR-007**: System MUST associate transactions with a credit card account owned by a
  user.
- **FR-008**: System MUST support detecting duplicate imported transactions.
- **FR-009**: System MUST allow transactions with an unresolved/unknown merchant.

### Key Entities *(include if feature involves data)*

- **User**: Owner of accounts; already exists in the app (Fortify auth).
- **Account (Credit Card)**: A card belonging to a user; groups transactions.
- **Merchant**: A normalized vendor where purchases occur; belongs to one category.
- **Category**: A user-defined spending group (Groceries, Dining, etc.).
- **Transaction**: A single purchase or refund tied to an account and a merchant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every recorded transaction is retrievable with its account, merchant, and
  (derived) category.
- **SC-002**: Per-category spending totals for any month match the sum of underlying
  transactions exactly.
- **SC-003**: Re-importing the same statement does not change reported totals.

## Assumptions

- Single-user-per-dataset local use; no multi-tenant production concerns (per constitution).
- Merchant→category is a one-to-many (a merchant has one category; a category has many
  merchants). Per-transaction category override is out of scope for v1.
- Amounts are stored in minor units (integer cents) to avoid floating-point drift.
- Import source format (CSV/OFX/manual) is out of scope here; this feature covers the
  schema and persistence only.
