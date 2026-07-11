# Quickstart: Deposits and Transfers

Verification is manual (Constitution Principle II — no automated tests). Run the app and confirm each step in the browser.

## Setup

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan transactions:classify-flow-types   # retroactive backfill (FR-016)
./vendor/bin/sail npm run dev
```

Open http://localhost.

## Before you start: record the baseline

On the dashboard, note the current spending total for the period covering your checking-account statement. It is almost certainly too high (transfers counted as expenses) or missing deposits entirely. This is the number the feature must fix.

## 1. Backfill classified existing data (US1, FR-016)

Go to **Transactions**. Every row now shows a flow-type badge. Confirm:

- Debit-card purchases → **Expense**.
- The paycheck deposit → **Income**.
- "TRANSFER TO SAVINGS", "PAYMENT THANK YOU", card autopay → **Transfer**.
- A store credit on a card account → **Refund**.

## 2. Spending excludes deposits and transfers (US1, FR-005/006/007)

On **Dashboard**:

- Spending total no longer includes the paycheck or any transfer.
- Hand-tally the period's real purchases; the total should match exactly (SC-001).
- The credit-card payment from checking is not counted, so card purchases appear exactly once.

Check the same on **Budgets**, **Insights**, and the category breakdown — no transfer or deposit appears in any of them. A refund should visibly reduce its category's total.

## 3. Income and net cash flow (US3, FR-013)

The dashboard shows income, spending, and net cash flow for the selected period. Confirm net = income − spending, and that transfers move neither figure. Switch periods with the period selector; all three follow.

## 4. Filtering (FR-012, FR-017)

On **Transactions**, filter by flow type. Selecting only *Transfer* lists transfers and nothing else; clearing the filter lists everything, including income and transfers — nothing is hidden, only excluded from the math.

## 5. Corrections stick (US2, FR-010/011, SC-007)

1. Find a misclassified row (e.g. a Zelle deposit the classifier called income but is really a refund from a friend). Change its flow type.
2. The dashboard totals update on the next view, with no re-import.
3. Re-import the same statement file. The corrected row keeps your classification — it is not overwritten (`flow_type_source = user`).
4. Import the *next* month's statement containing the same recurring merchant. It arrives already classified your way, via `merchants.default_flow_type`. No second correction needed.

## 6. Transfer pairing (FR-008)

Import statements for two accounts that both show the same transfer (checking → savings). Both legs are marked transfer, both are excluded from spending and income, and the pair is linked (`transfer_pair_id` set on both). Re-run the backfill command; the pairing does not duplicate.

## 7. Merchants page stays about spending (FR-015)

**Merchants** lists only merchants with expense activity by default — no payroll, no internal-transfer pseudo-merchants. Toggle "show non-expense merchants" to reveal them.

## Quality gates before finishing

```bash
./vendor/bin/sail composer run lint      # Pint
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run format
```
