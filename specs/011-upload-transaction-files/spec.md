# Feature Specification: Upload Transaction Files

**Feature Branch**: `011-upload-transaction-files`

**Created**: 2026-06-26

**Status**: Draft

**Input**: User description: "We need a way to upload transaction files from the front-end. The user will need to select the account the transactions belong to. The user will need to map the properties on the file to properties on the transaction. Ideally, they would select the file, the headers of the file would be read, and then those headers can be selected to map to transactions."

## Clarifications

### Session 2026-06-26

- Q: How should this feature relate to the existing back-end CSV import (feature 003), which assumed a fixed column layout and a single default account? → A: This feature supersedes 003's fixed-layout and default-account constraints, but reuses 003's underlying import engine (row→transaction conversion, duplicate detection, merchant matching).
- Q: When the user confirms the mapping, how should the import run? → A: Synchronously — the user waits on the upload screen until the import finishes and the result summary is shown inline.
- Q: Should column mappings be remembered to speed up repeat uploads? → A: Yes — persist mappings in a new model associated with a user and an account, and pre-fill the saved mapping on the next upload to that account (user can override).
- Q: Which optional transaction fields should the mapper expose beyond the required ones? → A: Any optional attributes that already exist on the Transaction model. In practice the required mappable fields are date (posted_at), amount, and description/merchant; the only optional mappable attribute currently on the model is currency. No new transaction fields are introduced.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Upload a file, map its columns, and import transactions (Priority: P1)

A signed-in user has exported a transactions file (e.g. a CSV from their bank) and wants to bring those transactions into the app. They open the upload area, choose the account the transactions belong to, and select the file. The system reads the file's column headers and presents them so the user can match each required transaction field (such as date, amount, and description/merchant) to a column in their file. Once the mapping is complete, the user confirms and the system imports each row as a transaction tied to the chosen account.

**Why this priority**: This is the core capability and the minimum viable slice. Without the ability to upload a file, pick an account, map columns, and create transactions, the feature delivers no value. Everything else builds on this flow.

**Independent Test**: Sign in, select an account, upload a well-formed file, map its headers to transaction fields, confirm, and verify each data row appears as a stored transaction on the selected account with the correct date, amount, and description.

**Acceptance Scenarios**:

1. **Given** a signed-in user on the upload screen, **When** they select an account and choose a supported file, **Then** the file's column headers are read and displayed for mapping.
2. **Given** the headers have been read, **When** the user maps each required transaction field to a header and confirms, **Then** every data row is imported as a transaction associated with the selected account.
3. **Given** a file with a refund/credit row (negative amount), **When** the import runs, **Then** the transaction is stored with the correct sign.
4. **Given** an import completes, **When** the result is shown, **Then** the user sees how many rows were imported, skipped, and failed.

---

### User Story 2 - Guided, validated column mapping (Priority: P2)

While mapping, the user is guided so they cannot submit an incomplete or invalid mapping. Required transaction fields must each be assigned to a header before import is allowed, and the system can pre-suggest mappings when a header name clearly matches a field (e.g. a "Date" column maps to the date field).

**Why this priority**: Mapping is the most error-prone step. Preventing invalid mappings and offering sensible suggestions dramatically reduces failed imports and user frustration. It builds directly on Story 1.

**Independent Test**: Open the mapping step with a file whose headers loosely match field names, confirm sensible mappings are pre-selected, remove a required mapping, and verify the import action is blocked with a clear message until it is restored.

**Acceptance Scenarios**:

1. **Given** a file whose headers resemble known fields, **When** the mapping step loads, **Then** matching fields are pre-mapped to those headers.
2. **Given** a required field is left unmapped, **When** the user attempts to import, **Then** import is blocked and the unmapped field is clearly indicated.
3. **Given** the same header is assigned to two different fields, **When** the user attempts to import, **Then** the conflict is flagged and import is blocked.

---

### User Story 3 - Preview rows before importing (Priority: P3)

Before committing, the user sees a small preview of how the first several rows of the file will be interpreted under the current mapping, so they can confirm the data looks right before importing everything.

**Why this priority**: A preview catches mapping mistakes (e.g. day/month swapped, amount mapped to the wrong column) before any data is written, but the feature still works without it. It is a confidence and quality-of-life enhancement.

**Independent Test**: Upload a file, set a mapping, and verify the preview shows the first few rows with values placed under the correct transaction fields; change a mapping and confirm the preview updates accordingly.

**Acceptance Scenarios**:

1. **Given** a mapping is in progress, **When** the user views the preview, **Then** the first several rows are shown with file values aligned to the transaction fields they are mapped to.
2. **Given** the preview is shown, **When** the user changes a column mapping, **Then** the preview reflects the new mapping.

---

### Edge Cases

- What happens when the selected file has no header row, or headers are blank/duplicated?
- How does the system handle a file that is empty, contains only headers, or has rows with missing required values?
- How does the system handle an unsupported file type or a file that exceeds the allowed size?
- What happens when a date or amount value cannot be parsed under the chosen mapping?
- What happens when the same file (or overlapping rows) is uploaded more than once — are duplicates avoided?
- What happens if the user navigates away or the upload fails partway through?
- How are values containing commas, quotes, or different decimal/thousand separators handled?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a signed-in user to upload a transaction file from the front-end.
- **FR-002**: System MUST require the user to select which of their accounts the uploaded transactions belong to before importing.
- **FR-003**: System MUST read and present the column headers from the uploaded file so they can be referenced during mapping.
- **FR-004**: System MUST let the user map each transaction field to a column header from the file.
- **FR-005**: System MUST define the mappable transaction fields as those existing on the Transaction model: required fields are date (posted_at), amount, and description/merchant; the only optional mappable field is currency. No new transaction fields are introduced by this feature.
- **FR-006**: System MUST prevent import until all required transaction fields are mapped to a header.
- **FR-007**: System MUST prevent or flag invalid mappings (e.g. the same header mapped to conflicting required fields).
- **FR-008**: System SHOULD pre-suggest mappings when a header name clearly corresponds to a transaction field, while allowing the user to override any suggestion.
- **FR-009**: System MUST import each data row as a transaction associated with the selected account using the confirmed mapping.
- **FR-010**: System MUST preserve the sign of amounts so credits/refunds reduce spend and charges increase it.
- **FR-011**: System MUST report the outcome of an import, including counts of rows imported, skipped, and failed.
- **FR-016**: System MUST process the import synchronously, keeping the user on the upload screen with a clear in-progress indication until the import completes and the result summary is shown inline.
- **FR-017**: System MUST persist a user's confirmed field-to-header mapping in a dedicated record associated with that user and the selected account.
- **FR-018**: System MUST pre-fill the saved mapping for an account on a subsequent upload to that account, allowing the user to override it before importing; a successful import updates the saved mapping for that account.
- **FR-012**: System MUST avoid creating duplicate transactions when a file or overlapping rows are uploaded more than once.
- **FR-013**: System MUST accept only CSV / delimited text files with a header row, and reject other formats (e.g. Excel, OFX/QFX) and oversized files with a clear message.
- **FR-014**: System MUST report rows that cannot be imported (e.g. unparseable date or amount) without aborting the rows that can be imported.
- **FR-015**: System MUST only allow a user to upload to, and import transactions onto, accounts they own.

### Key Entities *(include if feature involves data)*

- **Uploaded File**: The transaction file selected by the user. Key attributes: original file name, detected column headers, data rows, the selected target account, and the field-to-header mapping applied to it.
- **Field Mapping**: The association between each transaction field (date, amount, description/merchant, and any optional fields) and a column header in the uploaded file.
- **Saved Import Mapping**: A persisted record of a confirmed field-to-header mapping, associated with a specific user and account, used to pre-fill the mapping on subsequent uploads to that account. Key attributes: owning user, target account, and the stored field-to-header assignments.
- **Transaction**: An imported record tied to an account, with at least a date, an amount (signed), and a description/merchant, plus any optional mapped attributes. (Existing entity reused.)
- **Account**: The user-owned account that imported transactions are attached to. (Existing entity reused.)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can upload a file, select an account, complete the column mapping, and import transactions in under 3 minutes for a typical file.
- **SC-002**: After import, 100% of valid data rows in a well-formed file appear as transactions on the selected account with correct date, amount sign, and description.
- **SC-003**: Re-uploading the same file produces zero duplicate transactions.
- **SC-004**: At least 90% of users successfully complete an import on their first attempt without assistance.
- **SC-005**: For files whose headers resemble known fields, at least 80% of required fields are correctly pre-mapped without user changes.
- **SC-006**: When a file contains some invalid rows, all valid rows are still imported and every invalid row is reported back to the user.

## Assumptions

- The primary supported format for v1 is delimited text with a header row (e.g. CSV); other formats (e.g. spreadsheet or fixed-bank formats) are out of scope unless later specified.
- Accounts already exist and can be created/managed separately (see feature 010 — Manage Accounts); this feature only attaches transactions to existing accounts.
- The set of mappable transaction fields is derived from the existing Transaction model; required fields are at minimum date, amount, and description/merchant.
- Duplicate detection reuses the existing approach from the back-end import (account + date + amount + merchant), consistent with feature 003 — CSV Transaction Import.
- Merchant matching/creation behavior reuses existing application conventions (normalized-name match, create if no match).
- This front-end upload flow reuses the existing back-end import engine (feature 003) for row→transaction conversion, duplicate detection, and merchant matching, rather than introducing a separate import pipeline. It supersedes feature 003's fixed-column-layout and single-default-account constraints with flexible mapping and user-selected accounts.
- Imports are scoped to the authenticated user; cross-user access is not permitted.
