# HTTP Contracts: Merchants Module

All routes are registered under the `auth` + `verified` middleware group in
`routes/web.php` and are scoped to the authenticated user. Inertia handles
responses; mutations redirect back with an Inertia flash toast (matching the
Settings module convention). Wayfinder generates typed helpers under
`@/actions/*` and `@/routes/*` — the frontend imports those, never hardcoded
URLs.

## GET /merchants — `merchants.index`

Render the merchants management page.

- **Controller**: `MerchantController@index`
- **Inertia page**: `merchants/index`
- **Props**:
  - `merchants`: array of `{ id, name, display_name, label, category_id, transactions_count, aliases: [{ id, name }] }`
    for the authenticated user, ordered by `label`.
- **Auth**: own merchants only.

## PATCH /merchants/{merchant} — `merchants.update`

Set or clear a merchant's display name (FR-004).

- **Controller**: `MerchantController@update`
- **Form Request**: `UpdateMerchantRequest`
  - `display_name`: `nullable|string|max:200` (empty → stored as `null`)
- **Authorization**: `merchant.user_id === auth()->id()` else 403 (FR-011).
- **Response**: redirect back, flash `toast` success.

## POST /merchants/group — `merchants.group`

Group/merge two or more merchants into a primary (US2, FR-005–FR-007a).

- **Controller**: `MerchantGroupController@store`
- **Form Request**: `GroupMerchantsRequest`
  - `primary_merchant_id`: `required|integer|exists:merchants,id`
  - `merchant_ids`: `required|array|min:2` (distinct; must include the primary
    or be the full set being grouped)
  - `display_name`: `nullable|string|max:200` (FR-005a)
  - Rule: every id must belong to `auth()->user()` (FR-011); reject if fewer
    than 2 distinct merchants resolve (edge case no-op).
- **Behavior**: delegates to `MerchantGrouper` inside a DB transaction.
- **Response**: redirect back, flash `toast` summarizing the merge
  (e.g. "Grouped 3 merchants into Hy-Vee."). On authorization failure, 403 and
  no changes (US2 scenario 4).

## POST /merchants/{merchant}/aliases — `merchants.aliases.store`

Add an alias to a merchant (US3, FR-009/FR-010).

- **Controller**: `MerchantAliasController@store`
- **Form Request**: `StoreMerchantAliasRequest`
  - `name`: `required|string|max:200`
  - Rule: normalized value must be unique per user across all merchants
    (FR-010) — duplicate rejected with a validation message (US3 scenario 3);
    skip if it equals the merchant's own normalized name (edge case).
- **Authorization**: merchant belongs to the user.
- **Response**: redirect back, flash `toast`.

## DELETE /merchants/{merchant}/aliases/{alias} — `merchants.aliases.destroy`

Remove an alias (US3, FR-009).

- **Controller**: `MerchantAliasController@destroy`
- **Authorization**: both merchant and alias belong to the user, and the alias
  belongs to the merchant; else 403.
- **Response**: redirect back, flash `toast`.

## Notes

- No public/JSON API versioning is introduced; this is an internal Inertia SPA
  surface consistent with existing routes.
- Route-model binding is used for `{merchant}` / `{alias}`; per-user ownership
  is enforced in the form requests / controllers (or scoped bindings).
