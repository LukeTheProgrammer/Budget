# Feature Specification: Manage Accounts

**Feature Branch**: `010-manage-accounts`

**Created**: 2026-06-26

**Status**: Draft

**Input**: User description: "We need a way to create, edit, and delete Account models on the front-end."

## Clarifications

### Session 2026-06-26

- Q: How should an account's "type" be handled when creating/editing a manual account? → A: A predefined set selected from a dropdown (Checking, Savings, Credit, Cash, Investment), and the field is optional (may be left blank).
- Q: Where should the accounts management UI live in the app? → A: In the settings section (e.g. /settings/accounts), reusing the existing settings layout/navigation pattern.
- Q: When a manual account with transactions is deleted, what should happen to those transactions? → A: The account is soft-deleted and its transactions are hidden from normal views along with it, but both are retained in the database.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create a manual account (Priority: P1)

A signed-in user wants to track money held somewhere the app cannot connect to automatically (e.g. a cash wallet, a credit card, or a savings account). They open the accounts area, choose to add an account, give it a name and a few details, and save it. The new account immediately appears in their list of accounts and is available everywhere accounts are used (e.g. assigning transactions).

**Why this priority**: Without the ability to create an account, none of the other operations have anything to act on. This is the minimum viable slice that delivers value on its own — a user can start tracking a balance manually.

**Independent Test**: Sign in, add a new account with a name, confirm it appears in the account list and persists across a page reload.

**Acceptance Scenarios**:

1. **Given** a signed-in user with no accounts, **When** they add an account named "Cash Wallet", **Then** the account appears in their list and is owned by them.
2. **Given** the create form is open, **When** the user submits without a name, **Then** the form shows a validation error and no account is created.
3. **Given** a user creates an account, **When** they reload the page, **Then** the account is still present.

---

### User Story 2 - Edit an existing account (Priority: P2)

A user notices an account name is wrong, or wants to update details such as its type, currency, last four digits, or current balance. They open the account, change the relevant fields, and save. The updated details are reflected wherever the account is shown.

**Why this priority**: Editing builds on creation and is the next most common maintenance task, but the feature is still useful (MVP) without it.

**Independent Test**: With an existing account, change its name and balance, save, and confirm the new values appear in the list and persist.

**Acceptance Scenarios**:

1. **Given** an existing account, **When** the user changes its name and saves, **Then** the updated name is shown in the list.
2. **Given** the edit form is open, **When** the user clears the name and saves, **Then** a validation error is shown and the change is not saved.
3. **Given** an account linked to a financial institution, **When** the user edits it, **Then** they may change the display name but institution-derived fields are not editable.

---

### User Story 3 - Delete an account (Priority: P3)

A user no longer wants to track an account. They choose to delete it, confirm the action, and the account is removed from their list. The account's transactions are hidden from normal views along with it; both the account and its transactions are retained in the database (soft delete) rather than permanently erased.

**Why this priority**: Cleanup is valuable but the least urgent of the three operations; the feature delivers value without it.

**Independent Test**: With an existing account, delete it, confirm a confirmation step occurs, and that both it and its transactions disappear from normal views while their records remain in the database.

**Acceptance Scenarios**:

1. **Given** an existing account, **When** the user deletes it and confirms, **Then** it no longer appears in their account list.
2. **Given** an account with transactions, **When** it is deleted, **Then** the account and its transactions are hidden from normal views together, while both remain retained in the database.
3. **Given** a delete action, **When** the user is prompted to confirm, **Then** dismissing the prompt leaves the account unchanged.

---

### Edge Cases

- A user attempts to view, edit, or delete an account that belongs to another user — the action MUST be denied.
- A user submits a name longer than the allowed length — validation rejects it.
- A user enters a negative or non-numeric balance — the system handles it gracefully (negative balances are allowed for liabilities like credit cards; non-numeric is rejected).
- A user tries to delete an account linked to a financial institution — see Assumptions for how linked accounts are handled.
- Two rapid submissions of the create form — only one account is created (no duplicate from a double click).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Users MUST be able to create a new account by providing at minimum a name.
- **FR-002**: System MUST allow users to optionally specify an account's type, currency, last four digits, and current balance when creating or editing.
- **FR-002a**: System MUST present account type as an optional selection from a predefined set (Checking, Savings, Credit, Cash, Investment); the field may be left blank and free-form type values are not accepted.
- **FR-003**: System MUST associate every created account with the user who created it, and only show each user their own accounts.
- **FR-004**: Users MUST be able to edit the editable fields of an account they own.
- **FR-005**: Users MUST be able to delete an account they own, after an explicit confirmation step.
- **FR-006**: System MUST validate account input: name is required and within an allowed length; currency is a valid 3-letter code; balance is a valid monetary amount; last four is up to 4 digits.
- **FR-007**: System MUST prevent a user from viewing, editing, or deleting accounts owned by another user.
- **FR-008**: When an account is deleted, System MUST soft-delete it and hide its associated transactions from normal views together with it, while retaining both the account and transaction records in the database (no permanent erasure).
- **FR-009**: System MUST display the user's accounts in a list showing at least the name, type, and current balance.
- **FR-010**: For accounts linked to a financial institution, System MUST allow editing the display name only and MUST NOT permit editing institution-derived fields.
- **FR-011**: System MUST surface validation and authorization errors back to the user in a clear, actionable form.

### Key Entities *(include if feature involves data)*

- **Account**: Represents a place where the user holds or owes money. Belongs to one user. Key attributes: name, type (optional; one of a predefined set: Checking, Savings, Credit, Cash, Investment), currency, last four digits, current balance. May optionally be linked to a financial institution connection (linked accounts are imported, not manually created). An account has many transactions.
- **User**: The owner of accounts. Each user only sees and manages their own accounts.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can create a new account and see it in their list in under 30 seconds without external help.
- **SC-002**: 100% of accounts are visible only to their owning user; no user can access another user's account.
- **SC-003**: A user can complete create, edit, and delete of an account entirely from the front-end without leaving the app.
- **SC-004**: Deleting an account never permanently erases account or transaction records — both are retained in the database (0% data loss) even though they are hidden from normal views.
- **SC-005**: Invalid input (e.g. missing name) is rejected with a visible message 100% of the time, and never creates or corrupts an account.

## Assumptions

- Manual accounts (those not linked to a financial institution) are fully editable and deletable by their owner. Linked accounts (imported via an institution connection) are editable in name only and are not the primary target of this feature — disconnecting/removing a linked institution is handled by the existing integration flow, not by this delete action.
- Deletion is a soft delete: the account is hidden from the user and its associated transactions are hidden from normal views along with it, but both the account and transaction records are retained in the database, consistent with the existing data model.
- Negative balances are permitted to represent liabilities (e.g. credit cards). Balances are stored and displayed in the account's currency; USD is the default when none is specified.
- This feature targets the authenticated, signed-in web experience; there is no separate admin or bulk-management interface in scope.
- The accounts management UI lives in the settings section (e.g. /settings/accounts) and reuses the existing settings layout and navigation pattern.
- Currency is captured as a 3-letter code and defaults to USD; multi-currency conversion/reporting is out of scope for this feature.
