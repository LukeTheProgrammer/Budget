# Quickstart: Session Period Selector (Manual Verification)

> Per Constitution Principle II, this project has no automated tests. Verify manually in
> the running app.

## Setup

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

Sign in and ensure the user has some imported transactions across multiple months.

## Verification steps

1. **Selector in top nav** — On any authenticated page, confirm the period selector
   appears in the app header and shows the default **This month** (FR-001, FR-006).
2. **Preset applies to current page** — On the Dashboard, choose **Last month**; confirm
   the Dashboard data and the period label update without a manual refresh (FR-009).
3. **Global / persists across navigation** — Navigate to Transactions (once wired) and
   any other data page; confirm the selection is still **Last month** without
   re-selecting (FR-004, FR-005).
4. **Persists across reload** — Reload the page; confirm the selector still shows
   **Last month** (FR-005, SC-003).
5. **Custom range** — Choose **Custom range**, enter a valid start and end date, Apply;
   confirm data scopes to that inclusive range and the selector shows the range
   (FR-003, FR-008, FR-011).
6. **Invalid custom range** — Enter an end date earlier than the start (or leave one
   blank) and try to apply; confirm it is rejected with a clear message and not applied
   (FR-007, SC-005).
7. **Dashboard inline control removed** — Confirm the Dashboard no longer has its own
   period toggle; the top-nav selector is the single source of truth (FR-010).
8. **Session reset** — Sign out and back in; confirm the period returns to **This month**
   (default behavior per Assumptions).

## Quality gates (required before completion)

```bash
./vendor/bin/sail composer run lint        # Pint
npm run lint && npm run format && npm run types:check
```
