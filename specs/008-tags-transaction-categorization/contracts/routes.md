# HTTP Route Contracts: Tags

All routes are web (Inertia/session-authenticated) routes added to `routes/web.php` inside the existing authenticated group. Requests are issued from React via Wayfinder-generated helpers. Responses are Inertia redirects back (with flash) per the existing Merchant module convention, not JSON.

## Transaction tags

### POST `transactions/{transaction}/tags` — `transactions.tags.store`
Attach one or more tags to a transaction (create-on-the-fly by slug).

- **Body**: `{ "tags": string[] }` — raw display values.
- **Validation** (`SyncTransactionTagsRequest`): each value required, trimmed, ≤50 chars, letters/numbers/spaces/hyphens only.
- **Behavior**: for each value, `Tag::firstOrCreate` by slug, then `syncWithoutDetaching` onto the transaction (idempotent — a tag appears at most once).
- **Response**: redirect back with success flash.
- **Errors**: 422 on invalid tag value; 403 if the transaction is not in the user's accounts.

### DELETE `transactions/{transaction}/tags/{tag}` — `transactions.tags.destroy`
Detach a single tag from a transaction (does not delete the tag).

- **Params**: `{tag}` resolved by slug (route model binding on `slug`).
- **Behavior**: `detach` the tag from the transaction.
- **Response**: redirect back with success flash.
- **Errors**: 403 if the transaction is not in the user's accounts.

## Merchant default tags

### POST `merchants/{merchant}/default-tags` — `merchants.default-tags.store`
Add one or more default tags to a merchant.

- **Body**: `{ "tags": string[] }`.
- **Validation** (`SyncMerchantDefaultTagsRequest`): same value rules as above.
- **Behavior**: `firstOrCreate` by slug, then `syncWithoutDetaching` onto the merchant's `defaultTags`. Does NOT retroactively tag existing transactions (FR-013).
- **Response**: redirect back with success flash.
- **Errors**: 422 on invalid value; 403 if merchant not owned by the user.

### DELETE `merchants/{merchant}/default-tags/{tag}` — `merchants.default-tags.destroy`
Remove a default tag from a merchant (does not delete the tag or change existing transactions).

- **Behavior**: `detach` from the merchant's `defaultTags`.
- **Response**: redirect back with success flash.
- **Errors**: 403 if merchant not owned by the user.

## Global tag management

### DELETE `tags/{tag}` — `tags.destroy`
Delete a tag globally (FR-015). Cascades through both pivots, removing it from all transactions and merchant defaults.

- **Params**: `{tag}` resolved by slug.
- **Behavior**: delete the `Tag` row; DB cascade clears pivot links.
- **Response**: redirect back with success flash.
- **Note**: rename is intentionally not supported.

## Read paths (no new routes)

- `transactions/index` eager-loads `tags` and includes each transaction's tags in its row payload.
- `merchants/index` eager-loads `defaultTags` and includes them per merchant.
- Both pages receive the list of existing tags (slug + name) for autocomplete suggestions (hybrid entry).
