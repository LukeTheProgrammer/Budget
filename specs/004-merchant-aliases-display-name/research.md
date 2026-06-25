# Phase 0 Research: Merchant Display Names & Alias Grouping

All Technical Context items are resolved (no NEEDS CLARIFICATION remained after
`/speckit-clarify`). This document records the design decisions that shape
Phase 1.

## Decision 1 — Alias storage as a dedicated `merchant_aliases` table

- **Decision**: Store aliases in a separate `merchant_aliases` table with
  `(user_id, merchant_id, name, normalized_name)` and a unique index on
  `(user_id, normalized_name)`.
- **Rationale**: Multiple store variants must map to one merchant and remain
  queryable for import matching. A child table mirrors the existing
  `merchants.(user_id, normalized_name)` unique pattern and keeps lookups
  indexed. Free-text on the merchant row could not enforce per-user uniqueness
  or support efficient import matching.
- **Alternatives considered**:
  - *Parent/child self-reference on merchants* (rejected at clarify step — user
    chose the merge model; absorbed rows are deleted, not retained).
  - *JSON column of aliases on merchants* — cannot enforce DB-level per-user
    uniqueness across merchants; poor for indexed import lookups.

## Decision 2 — Grouping is a transactional merge in a `MerchantGrouper` service

- **Decision**: Encapsulate grouping in `App\Services\Merchants\MerchantGrouper`,
  run inside a single `DB::transaction`. Steps: validate all merchants belong to
  the acting user; for each absorbed merchant — reassign its transactions'
  `merchant_id` to the primary, create an alias on the primary for its raw name
  and copy over its existing aliases, then delete the absorbed merchant.
  Optionally set the primary's `display_name`.
- **Rationale**: A service keeps the merge atomic and reusable, matches the
  existing `CsvTransactionImporter` service convention, and guarantees FR-012
  (no orphaned/lost transactions) via the transaction boundary.
- **Alternatives considered**: Inline controller logic (rejected — not atomic,
  not reusable, violates Simplicity-with-clarity expectations for a multi-step
  mutation).

## Decision 3 — Alias-aware merchant resolution during CSV import

- **Decision**: In `CsvTransactionImporter::storeRow()`, before
  `Merchant::firstOrCreate`, look up a `MerchantAlias` by
  `(user_id, normalized_name = normalize(merchantName))`. If found, use its
  merchant; otherwise fall back to the existing `firstOrCreate` keyed on
  `(user_id, normalized_name)`.
- **Rationale**: Satisfies FR-014 with a minimal change at the single existing
  merchant-resolution point. Keeps one code path for all import entry points.
- **Import hash note**: `import_hash` already incorporates the resolved
  merchant's `normalized_name`. When an incoming name matches an alias, the
  resolved merchant is the primary, so the hash uses the primary's
  `normalized_name`. This is acceptable: dedup remains stable per resolved
  merchant, and pre-existing transactions are unaffected because their stored
  hashes are never recomputed.

## Decision 4 — Display name presentation via a model accessor

- **Decision**: Add a `display_name` nullable column. Expose a computed `label`
  (or reuse `display_name ?? name`) for UI consumption; the frontend always
  renders the label. `normalized_name` continues to be derived from `name` and
  drives matching (unchanged).
- **Rationale**: FR-002/FR-003 — friendly name everywhere, original always
  preserved and recoverable. Keeps normalization/matching decoupled from
  presentation.
- **Alternatives considered**: Overwriting `name` with the friendly value
  (rejected — loses the original imported name and would corrupt matching).

## Decision 5 — Normalization reuse

- **Decision**: Reuse the existing normalization (`mb_strtolower(trim($value))`)
  for alias `normalized_name`, applied via a `name` mutator on `MerchantAlias`
  mirroring `Merchant::name()`.
- **Rationale**: FR-013 — consistent matching/dedup across merchants and
  aliases. A future refactor could extract a shared helper, but duplicating the
  one-line rule now is simplest and avoids a new abstraction (Principle V).

## Decision 6 — UI surface

- **Decision**: Introduce a Merchants page (`resources/js/pages/merchants/index.tsx`)
  reached from the app nav, listing the user's merchants (showing the label and
  transaction count), with inline display-name editing, a multi-select grouping
  action (with optional display-name field for the primary), and per-merchant
  alias view/add/remove. Backend routes use Wayfinder-generated helpers.
- **Rationale**: No merchant-management UI exists yet; this is the minimal
  surface needed to exercise US1–US3. Uses existing shadcn/ui primitives.
- **Alternatives considered**: Embedding management inside a future transactions
  page (deferred — out of scope; a dedicated merchants view is the smallest
  independently demonstrable slice).
