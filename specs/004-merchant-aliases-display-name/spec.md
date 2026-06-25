# Feature Specification: Merchant Display Names & Alias Grouping

**Feature Branch**: `004-merchant-aliases-display-name`

**Created**: 2026-06-01

**Status**: Draft

**Input**: User description: "The merchant model should have a new column that is `display_name` that will be for the users of the app. Also, many merchants have multiple stores that should all be grouped together (e.g. "HY-VEE PR VILLAGE 1532" is just "Hy-Vee"). The app needs a way to create and manage aliases so that all of the transactions for a single merchant can be associated with each other."

## Clarifications

### Session 2026-06-01

- Q: When merchants are grouped, what happens to the absorbed merchant records? → A: Merge — absorbed merchants are deleted, their transactions move to the primary, and their raw names become aliases of the primary.
- Q: Should future CSV imports automatically match incoming raw names against existing aliases? → A: Yes — during import, match each incoming raw name (by normalized name) against existing aliases and link to that alias's merchant instead of creating a new merchant.
- Q: When grouping merchants, can the user name the resulting combined merchant in the same flow? → A: Yes — the grouping flow includes an optional display-name field for the primary merchant (defaults to its existing name).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Give a merchant a friendly display name (Priority: P1)

A user reviewing their transactions sees a merchant recorded with the raw,
machine-style name that came from their bank or card statement (e.g.
"HY-VEE PR VILLAGE 1532"). The user wants to set a clean, human-friendly name
(e.g. "Hy-Vee") that is shown throughout the app wherever that merchant
appears, without losing the original imported name.

**Why this priority**: This is the smallest standalone slice that delivers
immediate value — readable merchant names everywhere — and is a prerequisite
for meaningfully grouping merchants in later stories.

**Independent Test**: Set a display name on a single merchant and confirm the
app shows the friendly name (falling back to the original name when no display
name is set) on transaction lists and merchant views.

**Acceptance Scenarios**:

1. **Given** a merchant imported with the raw name "HY-VEE PR VILLAGE 1532" and no display name, **When** the user views the merchant, **Then** the app displays the raw name as the merchant label.
2. **Given** that merchant, **When** the user sets the display name to "Hy-Vee", **Then** the app shows "Hy-Vee" wherever that merchant is referenced while preserving the original imported name.
3. **Given** a merchant with a display name, **When** the user clears the display name, **Then** the app reverts to showing the original imported name.

---

### User Story 2 - Group multiple store variants into one merchant (Priority: P2)

A user notices several separate merchant entries that are really the same
business (e.g. "HY-VEE PR VILLAGE 1532", "HY-VEE #1099", "HYVEE FUEL 4021").
The user wants to combine them into a single merchant so all of their
transactions are associated together and reported as one merchant.

**Why this priority**: Grouping is the core value of the feature — it makes
spending-by-merchant accurate — but it builds on the ability (Story 1) to name
the resulting combined merchant.

**Independent Test**: Select two or more merchants belonging to the same user,
group them into one, and confirm that every transaction previously linked to
the grouped variants now resolves to the single combined merchant and that
spending totals reflect the combined set.

**Acceptance Scenarios**:

1. **Given** three separate merchants for the same user with transactions on each, **When** the user groups them under a chosen primary merchant, **Then** all transactions from the grouped merchants are associated with the primary merchant.
2. **Given** a completed grouping, **When** the user views spending by merchant, **Then** the grouped variants appear as a single merchant whose total is the sum of all member transactions.
3. **Given** a grouping, **When** the user views the primary merchant, **Then** the original raw names of the grouped variants are retained as aliases of that merchant.
4. **Given** the user attempts to group merchants, **When** any selected merchant belongs to a different user, **Then** the system rejects the action and no transactions are reassociated.

---

### User Story 3 - Manage a merchant's aliases (Priority: P3)

A user wants to view, add, and remove the alternate names (aliases) associated
with a merchant so they can correct mistaken groupings or pre-define names that
should map to a merchant.

**Why this priority**: Ongoing management and correction round out the feature
once grouping exists, but the app is already useful without manual alias
editing.

**Independent Test**: On a merchant with one or more aliases, add a new alias,
remove an existing alias, and confirm the alias list updates and the affected
transactions re-associate accordingly.

**Acceptance Scenarios**:

1. **Given** a merchant with two aliases, **When** the user views the merchant, **Then** all current aliases are listed.
2. **Given** a merchant, **When** the user removes an alias, **Then** that alias is no longer part of the merchant and the change is reflected immediately.
3. **Given** the user adds a name as an alias of merchant A, **When** that same name already belongs to merchant B for the same user, **Then** the system prevents the duplicate and informs the user.

---

### Edge Cases

- What happens when a user tries to group a merchant with itself or selects only one merchant? The action is a no-op and the user is informed.
- What happens to a merchant's category assignment when it is merged into a primary merchant that has a different category? The primary merchant's category is kept; the absorbed merchant's category assignment does not override it.
- What happens when the primary merchant of a grouping is later deleted? Its transactions and aliases are removed along with it, consistent with existing merchant deletion behavior.
- How does the system handle an alias whose text is identical (after normalization) to the primary merchant's own name? The alias is treated as already represented and not duplicated.
- What happens when two merchants being grouped each already have aliases? All aliases from every grouped merchant are retained under the primary merchant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow each merchant to have an optional user-facing display name that is distinct from the original imported name.
- **FR-002**: System MUST show the display name wherever a merchant is presented to the user, and MUST fall back to the original imported name when no display name is set.
- **FR-003**: System MUST preserve the original imported merchant name even after a display name is set or the merchant is grouped.
- **FR-004**: Users MUST be able to set, change, and clear a merchant's display name.
- **FR-005**: System MUST allow a user to group two or more of their own merchants into a single primary merchant.
- **FR-005a**: The grouping flow MUST allow the user to optionally set the primary merchant's display name as part of grouping, defaulting to the primary merchant's existing name.
- **FR-006**: When merchants are grouped, system MUST re-associate every transaction of the absorbed merchants with the primary merchant.
- **FR-007**: When merchants are grouped, system MUST retain the original imported names of the absorbed merchants as aliases of the primary merchant.
- **FR-007a**: When merchants are grouped, system MUST delete the absorbed merchant records after their transactions and names have been moved to the primary merchant.
- **FR-008**: System MUST allow a user to view the list of aliases for a merchant.
- **FR-009**: Users MUST be able to add and remove aliases for a merchant.
- **FR-010**: System MUST ensure aliases are unique per user, preventing the same alias name from being associated with more than one merchant for that user.
- **FR-011**: System MUST scope all merchant, alias, and grouping operations to the authenticated owning user and reject any operation that references another user's merchants.
- **FR-012**: System MUST ensure no transaction is orphaned or lost as a result of a grouping operation.
- **FR-013**: System MUST normalize alias and merchant names consistently when checking for duplicates and matches, mirroring the existing merchant name normalization.
- **FR-014**: During CSV import, system MUST match each incoming raw merchant name (by normalized name) against the user's existing aliases and, when matched, associate the transaction with that alias's merchant instead of creating a new merchant.

### Key Entities *(include if feature involves data)*

- **Merchant**: A business a user transacts with. Has an original imported name, a normalized matching key, and now an optional user-facing display name. Belongs to a user, optionally has a category, and owns transactions and aliases.
- **Merchant Alias**: An alternate name that resolves to a single merchant for a given user. Represents a store variant or alternate label (e.g. "HY-VEE PR VILLAGE 1532" as an alias of "Hy-Vee"). Each alias is unique per user and belongs to exactly one merchant.
- **Transaction**: A purchase associated with one merchant; after grouping, points to the primary merchant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can assign a friendly display name to a merchant and see it reflected across the app in under 30 seconds, with the original name still recoverable.
- **SC-002**: A user can combine multiple store variants into one merchant in a single workflow, after which 100% of the previously linked transactions appear under the single combined merchant.
- **SC-003**: After grouping, spending-by-merchant totals for the combined merchant equal the exact sum of all member transactions, with no double-counting or loss.
- **SC-004**: A user can add or remove an alias and see the merchant's alias list update immediately with no duplicate aliases across their merchants.
- **SC-005**: No grouping, aliasing, or display-name operation ever exposes or modifies another user's merchants or transactions.

## Assumptions

- Display name is optional; when absent the app falls back to the existing imported `name`, and the existing `normalized_name` continues to drive automatic matching during import.
- Aliases are stored as a distinct collection associated with a merchant (rather than free-text on the merchant) so that multiple store variants can map to one merchant and remain queryable.
- Grouping is performed by the user explicitly selecting a primary merchant; the system does not attempt automatic, fuzzy auto-grouping of merchants in this feature.
- When merchants are grouped, the absorbed merchant records are consumed into the primary merchant (their transactions and names move over) rather than left as empty duplicates.
- Uniqueness of aliases is enforced per user, consistent with the existing per-user uniqueness of merchant normalized names.
- This feature reuses the existing authentication and per-user data ownership model; all operations require an authenticated owning user.
- Future CSV imports automatically match incoming raw names against existing aliases (by normalized name); a match links the transaction to the alias's merchant rather than creating a new merchant (see FR-014).
