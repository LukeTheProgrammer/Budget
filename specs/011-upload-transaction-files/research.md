# Phase 0 Research: Upload Transaction Files

This feature has no `NEEDS CLARIFICATION` markers (resolved in `/speckit-clarify`). The research below records the design decisions needed to reuse the existing import engine and to handle file/format details the spec deliberately left to planning.

## D1. Reuse vs. rewrite of the import engine

**Decision**: Extract the row-storage logic (`storeRow`, `resolveMerchant`, `backfillCategory`, `applyDefaultTags`, `import_hash` computation) from `CsvTransactionImporter` into a new `TransactionRowStore` service that accepts an `Account` + a `NormalizedTransactionRow`. `CsvTransactionImporter` continues to handle Chase parsing and delegates storage to `TransactionRowStore`. A new `MappedCsvImporter` parses an uploaded CSV using a user-supplied column mapping into `NormalizedTransactionRow`s and delegates to the same store.

**Rationale**: Per clarification, this feature supersedes the fixed-layout/default-account constraints but reuses the engine. The dedup (`import_hash` = account + date + amountCents + merchant id) and merchant resolution must stay identical so files imported via either path de-duplicate against each other. Extraction avoids divergence and keeps one source of truth (Constitution V).

**Alternatives considered**: (a) Duplicate the storage logic in a second importer — rejected: drift risk, two dedup implementations. (b) Generalize `CsvTransactionImporter` in place with a mapping parameter — rejected: it is tied to fixed header validation, file discovery, and archiving that the upload flow does not use; a focused `MappedCsvImporter` is clearer.

## D2. Where headers are read (client vs. server)

**Decision**: Read headers and the preview rows **client-side** in React from the selected `File` (parse the first N lines with a small TS delimited-line parser). The authoritative full parse happens **server-side** in the single import request. No intermediate upload/temp-file endpoint and no server-side "read headers" round trip are introduced.

**Rationale**: Keeps the flow to one server write (the import), avoids a temp-file storage/cleanup layer, and gives instant header/preview feedback (Constitution I & V — simplicity, local-only). The server re-parses everything, so client parsing is advisory only and need not be perfectly robust.

**Alternatives considered**: Upload-first endpoint returning headers + a token, then a second import call referencing the token — rejected for v1: requires temp storage, token lifecycle, and cleanup for marginal benefit. (The `contracts/read-headers.md` documents the client-side contract; a server endpoint can be added later if needed.)

## D3. CSV parsing library

**Decision**: Server-side parsing uses PHP `SplFileObject` with `READ_CSV` flags, matching the existing `CsvTransactionImporter`. No new Composer dependency. Client-side uses a minimal hand-rolled parser that handles quoted fields and commas.

**Rationale**: Dependencies require explicit approval (Constitution / Tech Constraints). The existing importer already proves `SplFileObject` is sufficient for these files.

**Alternatives considered**: `league/csv` — more robust but a new dependency; not approved and not needed for v1.

## D4. Mappable fields and merchant handling

**Decision**: Mappable target fields = **date** (`posted_at`, required), **amount** (required), **description/merchant** (required, one column feeds both the transaction `description` and the merchant name resolved via `NameResolver`), and **currency** (optional; defaults to the selected account's currency when unmapped). No category mapping in v1 (Chase-specific backfill is bypassed; new merchants are created unconfirmed exactly as today).

**Rationale**: Matches the existing `Transaction` fillable columns and the clarified scope (only fields already on the model). Reusing the description-as-merchant approach keeps parity with `CsvTransactionImporter`, where the description column feeds `merchantName`.

## D5. Amount sign convention

**Decision**: The app convention is **positive = spend/purchase**. Because banks differ, the mapping includes an **amount-sign option**: `as_is` (file already uses positive = spend) or `invert` (file uses negative = spend, e.g. Chase). Default `as_is`. The chosen option is stored with the saved mapping.

**Rationale**: Without this, refunds/charges import with the wrong sign and break spend rollups (FR-010). A single boolean-style option covers the common cases without a full per-bank rules engine (YAGNI).

**Alternatives considered**: Separate debit/credit columns, or per-row sign from a "type" column — deferred; can be added later if a real file needs it.

## D6. Date parsing

**Decision**: Parse mapped date values by first attempting a small set of common formats (`Y-m-d`, `m/d/Y`, `d/m/Y`, `m/d/y`) and falling back to `CarbonImmutable::parse`. Rows whose date cannot be parsed are recorded as per-row failures (FR-014) without aborting the import.

**Rationale**: Generic uploads vary by locale/bank; tolerant parsing maximizes successful rows while still surfacing genuinely bad values. Ambiguous `d/m` vs `m/d` is an accepted v1 limitation (documented in quickstart).

## D7. Synchronous processing & upload limits

**Decision**: Run the import inside the controller request (FR-016) and return an Inertia redirect carrying the `ImportResult` summary (imported/skipped/failed/needs-review) via flash, with per-row failures surfaced to the user. Validate file size with Laravel's `max:` rule (e.g. a few MB) in addition to PHP's `upload_max_filesize`.

**Rationale**: Clarified decision is synchronous with inline summary. Typical statements are small enough to import within a request locally (Constitution I — no scaling concerns).

## D8. Saved mapping persistence

**Decision**: New `SavedImportMapping` model with `user_id`, `account_id`, and a JSON `mapping` payload (field → header name, plus `amount_sign` and optional `date_format`). Unique on (`user_id`, `account_id`) — one remembered mapping per account, upserted on each successful import. Pre-filled when the upload screen loads for an account.

**Rationale**: Clarified decision: a dedicated model associated with user + account, pre-filled next time, user-overridable. One-per-account keeps the model and UI simple; named templates were explicitly not chosen.

## D9. Routing & placement

**Decision**: Add `GET transactions/upload` (Inertia page, provides the user's accounts and any saved mappings) and `POST transactions/upload` (the import) under the existing authenticated `transactions` route group, in a new `UploadController`. The legacy `POST transactions/import` (Chase/default-account path) remains for the Artisan/back-end flow.

**Rationale**: Follows the existing Transactions module convention; keeps the new user-facing upload distinct from the legacy fixed-layout import without removing back-end capability.
