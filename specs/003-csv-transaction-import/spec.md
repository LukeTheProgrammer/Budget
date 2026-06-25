# Feature Specification: CSV Transaction Import

**Feature Branch**: `003-csv-transaction-import`

**Created**: 2026-06-01

**Status**: Draft

**Input**: User description: "I need a back-end process that will import transactions from a .csv file. The files that need to be imported are all in storage/app/private/*.csv. This process should be a service so it can be initiated by a front-end interaction, or a back end process like a command or a job."

## Clarifications

### Session 2026-06-01

- Q: What CSV column format should the import accept? → A: One fixed, documented column layout; reject files that don't match it.
- Q: How is the target account for an imported file determined? → A: A single configured/default account is used for v1 (one account total).
- Q: Which fields identify a duplicate transaction? → A: Account + date + amount + merchant together.
- Q: What happens to a CSV file after a successful import? → A: Move it to an archive/processed subfolder.
- Q: How are merchants from CSV rows matched to merchant records? → A: Match on normalized name (trim + case-fold); create if no match.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Import transactions from a CSV file (Priority: P1)

A user (or an automated process) points the system at a CSV file of credit card
purchases stored in the private file area, and the system reads each row and records it
as a transaction tied to the correct account and merchant.

**Why this priority**: This is the core capability. Without reliably turning a CSV row
into a stored transaction, the feature delivers no value. It is the minimum viable slice.

**Independent Test**: Place a known CSV file in the private storage area, run the import
against it, and confirm every valid row appears as a stored transaction with the correct
amount, date, and merchant.

**Acceptance Scenarios**:

1. **Given** a well-formed CSV file in the private storage area, **When** the import runs
   against that file, **Then** every data row becomes a stored transaction associated with
   the correct account and merchant.
2. **Given** a CSV containing a refund/credit row (negative amount), **When** the import
   runs, **Then** the transaction is stored with the correct sign so it reduces category
   spend.
3. **Given** an import completes, **When** the result is inspected, **Then** the system
   reports how many rows were imported, skipped, and failed.

---

### User Story 2 - Skip duplicate transactions on re-import (Priority: P2)

A user re-imports a statement that overlaps with previously imported data, and the system
recognizes already-imported rows and does not create duplicates.

**Why this priority**: Statements frequently overlap and files may be imported more than
once. Without de-duplication, totals become unreliable, undermining the analysis feature
this import feeds. It builds directly on Story 1.

**Independent Test**: Import the same file twice and confirm the second run creates no new
transactions and reports the overlapping rows as skipped duplicates.

**Acceptance Scenarios**:

1. **Given** a file has already been imported, **When** the same file is imported again,
   **Then** no duplicate transactions are created and the rows are reported as skipped.
2. **Given** a new file that partially overlaps a prior import, **When** it is imported,
   **Then** only the genuinely new rows are added.

---

### User Story 3 - Initiate import from multiple entry points (Priority: P2)

The same import capability can be triggered from a front-end interaction, an artisan
command, or a background job, without duplicating import logic.

**Why this priority**: The user explicitly requires the process to be reusable across
front-end and back-end triggers. Centralizing the logic in one service prevents divergent
behavior, but it depends on the core import (Story 1) existing first.

**Independent Test**: Trigger an import of the same file via the command-line entry point
and via a programmatic (front-end-style) call and confirm both produce identical results.

**Acceptance Scenarios**:

1. **Given** a CSV file in the private storage area, **When** the import is triggered from
   the command line, **Then** the transactions are imported with the same outcome as any
   other trigger.
2. **Given** a CSV file in the private storage area, **When** the import is triggered
   programmatically (as a front-end request would), **Then** the same import behavior and
   result reporting occur.

---

### Edge Cases

- A CSV row is malformed (missing required column, unparseable date or amount) — the row
  is skipped and reported as a failure without aborting the rest of the import.
- A merchant named in the CSV does not yet exist — the system creates it (matched on a
  normalized, trimmed/case-folded name) so the transaction can be stored.
- The CSV uses a header layout or column order that does not match the fixed expected
  layout — the whole file is rejected with a clear error before any rows are imported.
- The file is empty, contains only a header, or is not valid CSV.
- The single configured/default account does not exist or is not configured — the import
  reports a clear error and does not run.
- The same file is imported concurrently or while already in progress.
- Amounts use varied formats (currency symbols, thousands separators, parentheses for
  negatives).
- A very large file is imported (thousands of rows) and must complete without exhausting
  resources.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a single reusable import capability (a service) that can
  be invoked from a front-end interaction, an artisan command, and a background job
  without duplicating import logic.
- **FR-002**: System MUST read transaction rows from a CSV file located in the private
  application storage area (`storage/app/private/*.csv`).
- **FR-003**: System MUST parse each data row into a transaction with at minimum an amount,
  a transaction date, and a merchant reference, consistent with the existing transaction
  data model.
- **FR-003a**: System MUST accept a single fixed, documented CSV column layout and MUST
  reject (with a clear error) any file whose header/columns do not match that layout,
  rather than attempting to auto-detect alternative formats.
- **FR-004**: System MUST resolve or create the merchant referenced by each row so the
  transaction can be associated with a merchant record. Resolution MUST match on a
  normalized merchant name (trimmed and case-folded); a new merchant is created only when
  no normalized match exists.
- **FR-005**: System MUST associate each imported transaction with the single
  configured/default credit-card account for v1 (no per-file account selection or
  derivation from file contents).
- **FR-006**: System MUST preserve the sign of each amount so purchases and refunds/credits
  are stored correctly.
- **FR-007**: System MUST detect and skip rows that duplicate already-imported
  transactions so re-importing does not create duplicates. A duplicate is defined by the
  combination of account, transaction date, amount, and merchant.
- **FR-008**: System MUST continue processing remaining rows when an individual row fails
  validation, rather than aborting the whole import.
- **FR-009**: System MUST report a per-import summary including counts of rows imported,
  skipped (e.g., duplicates), and failed, with enough detail to identify failed rows.
- **FR-010**: System MUST be able to import a single named file, and MUST support
  discovering and importing the CSV files present in the private storage area.
- **FR-011**: System MUST validate that a target file exists and is readable before
  attempting to import, and report a clear error otherwise.
- **FR-012**: System MUST avoid partially recording a single row (each row is either fully
  imported or counted as failed/skipped).
- **FR-013**: System MUST move a CSV file to an archive/processed subfolder after it is
  successfully imported, so batch discovery does not reprocess it. A file that fails to
  import (e.g., rejected layout) MUST remain in place.

### Key Entities *(include if feature involves data)*

- **Import Source File**: A CSV file in the private storage area containing one transaction
  per data row.
- **Import Result**: The outcome of an import run — counts of imported, skipped, and failed
  rows plus details of failures.
- **Transaction**: A single purchase or refund created from a CSV row, tied to an account
  and a merchant (defined by the existing transaction data model, spec 002).
- **Merchant**: The vendor named in a CSV row; resolved or created during import.
- **Account (Credit Card)**: The card the imported transactions belong to.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Importing a well-formed CSV results in 100% of valid rows stored as
  transactions with correct amount, date, and merchant.
- **SC-002**: Re-importing a previously imported file creates zero new transactions and
  reports every overlapping row as a skipped duplicate.
- **SC-003**: An import containing some invalid rows still imports all valid rows and
  reports the exact count and identity of the failed rows.
- **SC-004**: The same file produces identical results regardless of whether the import is
  triggered from the command line, a background job, or a programmatic/front-end call.
- **SC-005**: Every import run returns a summary of imported, skipped, and failed counts.

## Assumptions

- The transaction, merchant, account, and category data model from spec
  `002-transaction-data-and` already exists and is the target for imported rows.
- Single-user-per-dataset local use; no multi-tenant production concerns (per constitution).
- Amounts are stored in minor units (integer cents) to avoid floating-point drift,
  consistent with spec 002.
- CSV files are encoded as UTF-8 and the first row is a header.
- Duplicate detection uses the natural identity of a transaction — account, date, amount,
  and merchant together — rather than a separate file-level marker.
- All imported transactions belong to a single configured/default account for v1; richer
  account-selection (per-file or derived from file contents) is out of scope.
- The import reads files already present in `storage/app/private/`; uploading files into
  that location is a separate concern handled by the triggering interaction.
