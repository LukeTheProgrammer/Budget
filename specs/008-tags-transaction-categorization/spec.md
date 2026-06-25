# Feature Specification: Tags for Transaction Categorization

**Feature Branch**: `008-tags-transaction-categorization`

**Created**: 2026-06-06

**Status**: Draft

**Input**: User description: "Create a new model: \"Tags.\" Tags are ways to categorize transactions and are simple string values. Use a slug version of the tag as the primary key instead of an integer. Create the ability to set default tags for a given merchant. Make it so the default merchant tags are added to transactions when they are imported. Create a one-to-many relationship to Transaction so that many tags can be applied to each Transaction."

## Clarifications

### Session 2026-06-06

- Q: Tag ↔ Transaction relationship cardinality? → A: Many-to-many (a shared, slug-keyed tag links to many transactions; each transaction has many tags).
- Q: How do users enter tags? → A: Hybrid — free-form entry with autocomplete suggestions drawn from existing tags; a new tag is created on the fly if no slug match exists.
- Q: Tag value constraints? → A: Trimmed; max 50 characters; allow letters, numbers, spaces, and hyphens; reject empty/whitespace-only.
- Q: When are merchant default tags applied automatically? → A: Import only — not on manual creation or later merchant assignment.
- Q: Global tag management scope? → A: Delete only — users can delete a tag globally (removing it from all transactions and merchant defaults); renaming is out of scope.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Apply tags to transactions (Priority: P1)

A user wants to categorize their transactions so they can understand and group their spending. They apply one or more short text labels ("tags") to a transaction, and those tags stick to the transaction so they can be seen and filtered later.

**Why this priority**: Tagging transactions is the core value of the feature. Without it, none of the other capabilities (defaults, import automation) have any meaning.

**Independent Test**: Open a transaction, add one or more tags to it, reload, and confirm the tags persist and are displayed. Can be tested in isolation without merchants or import.

**Acceptance Scenarios**:

1. **Given** a transaction with no tags, **When** the user adds the tag "Groceries", **Then** the transaction displays the "Groceries" tag and it persists across reloads.
2. **Given** a transaction tagged "Groceries", **When** the user adds a second tag "Essentials", **Then** the transaction shows both tags.
3. **Given** a transaction tagged "Groceries", **When** the user removes the "Groceries" tag, **Then** the transaction no longer shows that tag.
4. **Given** the user types a tag that differs only by casing or spacing from an existing tag (e.g. "Dining Out" vs "dining out"), **When** they save it, **Then** the system treats both as the same tag rather than creating a duplicate.

---

### User Story 2 - Set default tags for a merchant (Priority: P2)

A user wants transactions from a particular merchant to be categorized consistently without manual effort each time. They define one or more default tags for a merchant.

**Why this priority**: Defaults remove repetitive manual tagging and are the setup step that makes automated tagging on import (Story 3) possible.

**Independent Test**: Open a merchant, assign default tags, reload, and confirm the defaults are saved and shown.

**Acceptance Scenarios**:

1. **Given** a merchant with no default tags, **When** the user assigns "Coffee" and "Discretionary" as defaults, **Then** those defaults are saved and displayed on the merchant.
2. **Given** a merchant with default tags, **When** the user removes a default tag, **Then** it no longer appears as a default for that merchant and existing transactions are not changed.

---

### User Story 3 - Automatically tag transactions on import (Priority: P2)

When a user imports transactions, each imported transaction should automatically receive the default tags configured for its merchant, so categorization happens without manual work.

**Why this priority**: This is the payoff of Story 2 and the main labor-saving benefit, but it depends on both tagging (P1) and merchant defaults (P2) existing first.

**Independent Test**: Configure default tags on a merchant, import a transaction belonging to that merchant, and confirm the imported transaction carries the merchant's default tags.

**Acceptance Scenarios**:

1. **Given** a merchant with default tags "Coffee" and "Discretionary", **When** a transaction for that merchant is imported, **Then** the imported transaction is created with both default tags applied.
2. **Given** a merchant with no default tags, **When** a transaction for that merchant is imported, **Then** the transaction is imported with no tags and no error occurs.
3. **Given** a transaction is imported for a merchant whose default tag already exists in the system, **When** the import runs, **Then** the existing tag is reused rather than duplicated.

---

### Edge Cases

- What happens when a user enters an empty or whitespace-only tag? It MUST be rejected and not saved.
- What happens when two different display strings produce the same slug? They MUST resolve to the same tag.
- What happens when a tag is removed from a merchant's defaults after transactions have already been imported with it? Previously imported transactions keep the tag; only future imports change.
- What happens when an import would apply the same default tag twice to one transaction? The tag MUST appear only once on that transaction.
- What happens when a transaction has no associated merchant during import? It is imported with no default tags applied.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a tag to be created as a simple, short text value.
- **FR-002**: System MUST identify each tag by a slug derived from its text value, used as its unique identifier instead of a numeric identifier.
- **FR-003**: System MUST treat text values that produce the same slug as the same tag (case- and spacing-insensitive equivalence), preventing duplicate tags.
- **FR-004**: System MUST reject empty or whitespace-only tag values, trim surrounding whitespace, limit values to 50 characters, and allow only letters, numbers, spaces, and hyphens.
- **FR-005**: Users MUST be able to apply one or more tags to a transaction via free-form text entry with autocomplete suggestions sourced from existing tags; entering a value whose slug matches no existing tag MUST create that tag on the fly.
- **FR-006**: A transaction MUST be able to carry multiple tags simultaneously.
- **FR-007**: Users MUST be able to remove a tag from a transaction without deleting the tag itself.
- **FR-008**: Users MUST be able to define a set of default tags for a merchant.
- **FR-009**: Users MUST be able to add and remove a merchant's default tags.
- **FR-010**: System MUST apply a merchant's default tags to each transaction created for that merchant during import only; defaults MUST NOT be auto-applied on manual transaction creation or on later merchant assignment.
- **FR-011**: System MUST reuse an existing tag (matched by slug) rather than creating a duplicate when applying or assigning tags.
- **FR-012**: System MUST ensure a given tag appears at most once on a single transaction.
- **FR-013**: Changing a merchant's default tags MUST NOT retroactively alter tags on previously imported transactions.
- **FR-014**: System MUST allow transactions without an associated merchant, or whose merchant has no defaults, to be imported with no tags and without error.
- **FR-015**: Users MUST be able to delete a tag globally, which removes it from all transactions and from all merchant defaults; renaming tags is out of scope.

### Key Entities *(include if feature involves data)*

- **Tag**: A short text label used to categorize transactions. Uniquely identified by a slug derived from its text value (no separate numeric key). The same tag may be applied to many transactions and may serve as a default for many merchants.
- **Transaction**: An existing entity that can have zero or more tags applied to it.
- **Merchant**: An existing entity that can have zero or more default tags. Its defaults are applied to its transactions at import time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can apply a tag to a transaction and see it persist in under 5 seconds with no more than two interactions.
- **SC-002**: 100% of transactions imported for a merchant with default tags receive exactly that merchant's default tags, with no duplicates.
- **SC-003**: No duplicate tags exist for text values that share the same slug, verified across all created tags.
- **SC-004**: Editing a merchant's defaults changes 0% of previously imported transactions' tags.

## Assumptions

- The tag↔transaction and tag↔merchant-default associations are many-to-many (see Clarifications). The user's original "one-to-many" phrasing is satisfied by this model since many tags apply to each transaction.
- Tags are global/shared across the application rather than scoped per user, consistent with the current single-tenant scaffolding.
- Tag display preserves the user's entered casing where shown, while equivalence and uniqueness are determined by the slug.
- Import refers to the existing CSV transaction import flow (feature 003); default-tag application hooks into that existing process.
- Removing the last transaction that references a tag does not automatically delete the tag.
