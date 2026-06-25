# Feature Specification: Plaid Bank Account Integration

**Feature Branch**: `009-plaid-bank-integration`

**Created**: 2026-06-24

**Status**: Draft

**Input**: User description: "I need to build an integration with the Plaid API. I want to connect this app to my banking accounts so I can pull real time data about my spending."

## Clarifications

### Session 2026-06-24

- Q: How should Plaid-linked accounts relate to the existing `Account` model? → A: Reuse the existing `Account` model; a linked account is an `Account` row with a nullable reference to its connection plus the provider's account id. Manual, CSV, and linked accounts share one table.
- Q: How should linked accounts receive updates to satisfy the "real time" goal? → A: Manual/on-demand sync only for v1; webhook-driven and scheduled automatic refresh are deferred to a later iteration.
- Q: How should imported transactions handle duplicates / collisions with existing data? → A: No cross-source reconciliation in v1. Reuse the existing `Transaction.import_hash` + `updateOrCreate` mechanism (as the CSV importer does) so a stable provider-derived hash collision updates the existing row instead of creating a duplicate. Overlaps with separately-entered manual/CSV transactions are not auto-merged.
- Q: Which Plaid environment should v1 target? → A: Build against Sandbox for dev/test, with the environment selectable via configuration so Production can be enabled later without code changes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Connect a bank account (Priority: P1)

A user securely links one of their real banking or credit card accounts to the app by
authenticating with their financial institution through the provider's hosted connection
flow, so the app can begin importing their spending data automatically.

**Why this priority**: Nothing else in this feature is possible until an account is
linked. A single successful connection that lists the user's real accounts is a complete,
demonstrable slice of value.

**Independent Test**: Start the connection flow, authenticate with a financial
institution, and confirm the linked account(s) appear in the app with institution name,
account name, account type, and current balance.

**Acceptance Scenarios**:

1. **Given** an authenticated app user, **When** they start the connect flow and
   successfully authenticate with their institution, **Then** the selected account(s) are
   saved and shown as linked with institution name, account name, last four digits, type,
   and current balance.
2. **Given** a user mid-connection, **When** they cancel or fail institution
   authentication, **Then** no partial account is created and they can retry.
3. **Given** a user who already linked an institution, **When** they link a second
   institution, **Then** both connections and all their accounts are listed separately.

---

### User Story 2 - Import transactions from a linked account (Priority: P1)

After linking an account, the user's historical and ongoing transactions are pulled into
the app and surfaced as spending data, so the existing transaction, merchant, and
category features work against real bank data instead of manual entry or CSV import.

**Why this priority**: Importing transactions is the core payoff the user asked for —
"pull real time data about my spending." It depends only on Story 1.

**Independent Test**: Link an account with transaction history, trigger a sync, and
confirm transactions appear with amount, date, description/merchant, and the correct
linked account, without duplicates.

**Acceptance Scenarios**:

1. **Given** a newly linked account, **When** the initial import completes, **Then**
   available historical transactions are stored and associated with that account.
2. **Given** transactions already imported, **When** a subsequent sync runs and the same
   transaction is returned again, **Then** it is not duplicated.
3. **Given** the provider reports a pending transaction that later posts (amount or date
   changes), **When** the next sync runs, **Then** the stored transaction is updated to
   the posted values rather than duplicated.
4. **Given** the provider reports that a previously imported transaction was removed,
   **When** the next sync runs, **Then** that transaction is removed or marked voided so
   spending totals stay accurate.

---

### User Story 3 - Refresh spending data on demand (Priority: P2)

The user triggers a sync for a linked connection whenever they want up-to-date data, and
the app pulls any new transactions and updated balances since the last sync.

**Why this priority**: A working user-triggered refresh delivers the core "current data"
value without requiring webhook infrastructure. Fully automatic background refresh is
deferred to a later iteration (see Assumptions).

**Independent Test**: After new activity exists at the institution, trigger a manual sync
and confirm the new transactions and updated balances appear without re-linking.

**Acceptance Scenarios**:

1. **Given** a linked account with new activity since the last sync, **When** the user
   triggers a sync, **Then** the new transactions are imported.
2. **Given** a user-triggered sync, **When** account balances have changed, **Then** the
   stored balance for each linked account is updated.

---

### User Story 4 - Manage and disconnect connections (Priority: P3)

The user can view their linked connections, see connection health (e.g., needs
re-authentication), re-authenticate when a bank requires it, and disconnect an account so
it stops syncing and its credentials are revoked.

**Why this priority**: Lifecycle management matters for trust and data hygiene but is not
required to prove the core import value.

**Independent Test**: Disconnect a linked institution and confirm it no longer syncs and
is removed from the linked-accounts list; trigger a re-auth required state and confirm the
user is prompted to fix it.

**Acceptance Scenarios**:

1. **Given** a linked institution, **When** the user disconnects it, **Then** syncing
   stops, stored access credentials are revoked, and the connection is removed from the
   list.
2. **Given** a connection that the institution has invalidated, **When** the user views
   their connections, **Then** it is shown as needing attention with a way to
   re-authenticate.
3. **Given** a disconnected institution, **When** the user views past spending, **Then**
   previously imported transactions remain available unless the user explicitly deletes
   them.

---

### Edge Cases

- A user links an institution whose account overlaps with transactions previously added
  manually or via CSV — v1 does not auto-merge these; only same-source repeated imports are
  de-duplicated via `import_hash`.
- The provider returns a transaction in a foreign currency or with a currency different
  from the account's default.
- An institution connection requires periodic re-authentication (expired consent) — the
  user must be prompted before syncing resumes.
- The provider is temporarily unavailable or rate-limits requests during a sync.
- A linked account is closed at the bank — future syncs return no data and the connection
  should reflect this rather than erroring repeatedly.
- A refund/credit (positive cash flow) must reduce category spend consistently with how
  manual transactions are signed.
- Initial historical import is large and must complete without blocking the user's session.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST let an authenticated user initiate a secure connection flow to a
  financial institution and complete it without the app ever handling raw banking
  credentials directly.
- **FR-002**: System MUST persist a linked connection per user that represents access to
  one institution, including the institution identity and connection status.
- **FR-003**: System MUST retrieve and store the accounts available under a connection,
  including account name, type, last four digits, and current/available balance.
- **FR-004**: System MUST import transactions for each linked account and store them using
  the app's existing transaction model (amount, currency, date, description/merchant,
  account reference).
- **FR-005**: System MUST avoid creating duplicate transactions across repeated syncs of
  the same account by reusing the existing `Transaction.import_hash` mechanism with
  `updateOrCreate` (consistent with the CSV importer): a stable provider-derived hash
  collision updates the existing row rather than inserting a duplicate.
- **FR-006**: System MUST apply provider updates to previously imported transactions,
  including pending-to-posted changes and removals/voids, via the same hash-keyed upsert.
- **FR-006a**: System MUST NOT perform cross-source reconciliation in v1 — transactions
  separately entered manually or via CSV are not auto-merged with provider imports.
- **FR-007**: System MUST allow the user to trigger a sync for a connection on demand,
  importing new transactions and updated balances since the last sync. (Fully automatic
  background refresh is deferred to a later iteration.)
- **FR-009**: System MUST surface connection health to the user (healthy, needs
  re-authentication, error) and allow the user to re-authenticate an invalidated
  connection.
- **FR-010**: System MUST allow the user to disconnect a connection, which stops future
  syncing and revokes stored access with the provider.
- **FR-011**: System MUST store provider access secrets securely (encrypted at rest) and
  never expose them to the frontend.
- **FR-012**: System MUST associate every imported transaction and account with the owning
  user so that data is scoped per user.
- **FR-013**: System MUST attempt to resolve imported transactions to merchants and
  categories using the existing categorization features, falling back to "Uncategorized"
  when no match exists.
- **FR-014**: System MUST handle provider errors and outages gracefully, retrying
  transient failures and reporting persistent ones without losing already-imported data.
- **FR-015**: System MUST keep previously imported transactions available after a
  connection is disconnected, unless the user explicitly deletes them.

### Key Entities *(include if feature involves data)*

- **User**: Owner of connections and all imported data; already exists (Fortify auth).
- **Connection (Linked Item)**: A user's authorized link to one financial institution,
  holding institution identity, status/health, and the secure access reference used to
  fetch data. Has many linked accounts.
- **Institution**: The bank or card issuer a connection authenticates against (name,
  logo/identity).
- **Linked Account**: An existing `Account` row that carries a nullable reference to its
  connection plus the provider's account id; holds name, type, mask (last four), and
  balance. Manual, CSV, and linked accounts share the one `Account` table.
- **Transaction**: A single imported purchase, refund, or credit tied to a linked account;
  reuses the existing transaction entity, with a stable provider-derived value stored in
  the existing `import_hash` field used by `updateOrCreate` for de-duplication and update
  matching.
- **Sync State / Cursor**: Per-connection bookkeeping that records how far transactions
  have been imported so incremental syncs fetch only new changes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can link a real banking account and see their accounts listed in
  under 3 minutes from starting the connect flow.
- **SC-002**: After linking, the user's available transaction history is imported and
  visible without any manual data entry.
- **SC-003**: A user-triggered sync of a linked connection imports all new activity
  available from the provider and completes within 30 seconds for a typical account.
- **SC-004**: Re-running a sync never changes reported category totals when no new bank
  activity has occurred (idempotent imports — zero duplicates).
- **SC-005**: Disconnecting a connection stops all further syncing for that connection and
  removes the app's ability to access the institution.
- **SC-006**: 100% of imported transactions are attributed to the correct linked account
  and owning user.

## Assumptions

- **Plaid is the chosen banking data provider.** The connection flow uses the provider's
  hosted/secure link experience so the app never receives raw bank credentials.
- Scope for v1 is **account balances and transactions** (depository and credit card
  accounts). Investments, loans/liabilities, and bill-pay/transfers are out of scope.
- "Real time" for v1 is satisfied by **user-triggered on-demand sync**. Webhook-driven and
  scheduled automatic refresh are explicitly **deferred to a later iteration**; true
  instantaneous updates depend on the institution and provider and are not guaranteed.
- The build targets the provider's **Sandbox environment** for development and testing,
  with the active environment **selectable via configuration** so Production can be enabled
  later without code changes. Production banking access requires the user to supply
  production provider credentials and any required provider approval — treated as an
  operational prerequisite, not app logic.
- Amounts continue to be stored in **minor units (integer cents)** consistent with the
  existing transaction model, with the provider's sign convention normalized to the app's.
- Single-user-per-dataset local use consistent with the existing app; no multi-tenant
  production concerns beyond per-user data scoping.
- Imported accounts integrate with the **existing Account, Merchant, Category, and
  Transaction** features (specs 002–008) rather than introducing a parallel data model.
- Initial historical import runs as **background work** so it does not block the user's
  session.
- Provider API keys/secrets are supplied via application configuration (environment), not
  committed to the repository.
