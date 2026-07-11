# Feature Specification: Deposits and Transfers

**Feature Branch**: `012-transaction-flow-types`

**Created**: 2026-07-11

**Status**: Draft

**Input**: User description: "One of the accounts I added is my checking account with my bank and the transactions from that account contain deposits and transfers, and those need to be handled differently from expenses. Please review the app and make a plan on how to handle deposits and transfers."

## Context

Today every transaction is treated as a purchase. Spending totals, category breakdowns, budgets, trends, recent/largest lists, and merchant totals all use a single rule: a positive amount is spending, a negative amount is ignored. That rule breaks down for a checking account, where the same feed carries:

- **Deposits** — paychecks, refunds from a person, interest. These are income, not negative spending, and they should not silently disappear.
- **Transfers** — money moved between the user's own accounts (checking → savings, checking → credit-card payment). These are not income or spending on either side. Counting a credit-card payment as an expense double-counts the purchases already imported from the card.
- **Refunds/returns** — a credit from a merchant that should reduce spending in that merchant's category.
- **Expenses** — ordinary purchases.

Because the app has no concept of what kind of money movement a transaction represents, a checking account currently either inflates spending (transfers out counted as expense) or hides money entirely (deposits dropped from every view).

## Clarifications

### Session 2026-07-11

- Q: When a user corrects a transaction's flow type, does the correction generalize? → A: It applies to that transaction AND creates/updates a reusable per-merchant rule, so future matching rows classify the same way; individual rows can still be overridden.
- Q: How strictly must the two legs of a transfer match to be auto-paired? → A: Exact amount, opposite direction, different accounts of the same user, same currency, posted within ±3 days.
- Q: Do income transactions roll up to categories? → A: No. Income is reported as a single total; categories and budgets remain expense-only.
- Q: How is a refund distinguished from income (both are inflows)? → A: Account-type aware — on a credit account, an inflow that is not a recognized card payment is a refund; on other accounts, an inflow is a refund only if the user has prior expense transactions with that same merchant, otherwise it is income.
- Q: Should transfer/income rows appear on the Merchants page? → A: They still resolve to a merchant (rules key on it), but the Merchants page lists only merchants with expense activity by default; the rest are reachable via a filter/toggle.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Deposits and transfers stop polluting spending (Priority: P1)

A user imports a checking-account statement containing a paycheck deposit, a transfer to savings, a credit-card payment, and normal debit-card purchases. The app classifies each row by what kind of money movement it is, and spending figures reflect only real purchases.

**Why this priority**: Without this, every spending number the app shows for a checking-account user is wrong. It is the whole point of the feature and it stands alone as an MVP.

**Independent Test**: Import a checking statement with one deposit, one transfer out, one credit-card payment, and three purchases; confirm the dashboard spending total equals only the three purchases, and that the deposit and transfers are excluded from spending, category totals, budgets, and trends.

**Acceptance Scenarios**:

1. **Given** a checking account with a $2,400 paycheck deposit, **When** the user views the dashboard for that period, **Then** total spending does not include the $2,400 and the deposit is reported as income.
2. **Given** a $500 transfer from checking to the user's savings account, **When** the user views spending totals, category breakdown, budgets, and monthly trend, **Then** the $500 appears in none of them.
3. **Given** a $1,200 payment from checking to the user's credit card whose purchases are already imported, **When** the user views spending for the period, **Then** the $1,200 is not counted, so those purchases are counted exactly once.
4. **Given** a $60 refund from a merchant on a card account, **When** the user views that merchant's category total, **Then** the category total is reduced by $60 rather than the refund being ignored.
5. **Given** any transaction, **When** the user opens the transactions list, **Then** each row shows what kind of money movement it is (expense, income, transfer, refund).

---

### User Story 2 - Correcting a misclassified transaction (Priority: P2)

Automatic classification will not be perfect (e.g. a Zelle deposit from a friend, a "transfer" that is really a payment to a person). The user can change the kind of any transaction, and all totals update accordingly.

**Why this priority**: Classification confidence is what makes the numbers trustworthy; the user needs an escape hatch, but it is only useful once P1 exists.

**Independent Test**: Change a transaction the app called a transfer into an expense, and confirm the dashboard spending total rises by that amount.

**Acceptance Scenarios**:

1. **Given** a transaction classified as a transfer, **When** the user changes it to an expense, **Then** it immediately counts toward spending, its category, and its budget.
2. **Given** a transaction the user has manually classified, **When** the statement is re-imported or the account re-syncs, **Then** the user's classification is preserved and not overwritten by automatic rules.
3. **Given** a transaction changed to income, **When** the user views spending, **Then** it is excluded from spending and included in income.

---

### User Story 3 - Seeing income and net cash flow (Priority: P3)

The user wants to see what came in, not just what went out: income for the period, spending for the period, and the net difference.

**Why this priority**: Once deposits are captured correctly, surfacing them is the payoff — but the app is already correct without it.

**Independent Test**: With a month containing $4,000 of deposits and $3,100 of purchases, confirm the dashboard reports income $4,000, spending $3,100, and net +$900.

**Acceptance Scenarios**:

1. **Given** a period with deposits and purchases, **When** the user views the dashboard, **Then** income, spending, and net cash flow for the selected period are shown.
2. **Given** transfers between the user's own accounts in that period, **When** the user views income and net cash flow, **Then** the transfers affect neither figure.
3. **Given** the transactions list, **When** the user filters by kind of money movement, **Then** only transactions of the selected kind(s) are listed.

---

### Edge Cases

- **One-sided transfers**: only one leg of a transfer is visible because the other account is not tracked in the app (e.g. checking → an external brokerage). The transaction is still classified as a transfer and excluded from spending; no pairing is required.
- **Both legs present**: the outgoing leg in checking and the incoming leg in savings are both imported. The pair must be recognized so the money is not counted as income on the receiving side, and the pair is shown as a single logical movement where transfers are listed.
- **Near-miss pairing**: legs post more than 3 days apart, or with unequal amounts (fees), or in different currencies. They are not auto-paired; each stays an unpaired transfer and is still excluded from spending and income.
- **Competing pairing candidates**: two identical-amount transfers within the window could each pair with the same counterpart. Each transaction participates in at most one pair; the closest-dated candidate wins.
- **Merchant with both purchases and a paycheck**: a merchant the user has spent with also sends an inflow that is genuinely income (e.g. an employer the user also buys from). It is auto-classified as a refund; the user's correction reclassifies it and, via the merchant rule, all future inflows from that merchant.
- **Refund larger than spending**: a category's refunds exceed its purchases for the period, producing a negative category total. It is displayed as negative rather than clamped to zero.
- **Existing data**: transactions imported before this feature exists must be classified retroactively; a user who has already imported a checking statement should see corrected numbers without re-importing.
- **Budgets**: a category whose transactions are all refunds/income must not show as "over budget".
- **Merchant totals**: the merchant list total for a merchant with refunds nets them out; income- and transfer-only merchants are excluded from the default merchant list.
- **Ambiguous deposits**: a deposit whose counterpart cannot be determined (cash deposit, Zelle from a person) defaults to income and is correctable by the user.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Every transaction MUST carry exactly one flow type identifying the kind of money movement: **expense**, **income**, **transfer**, or **refund**.
- **FR-002**: The system MUST assign a flow type automatically at import/sync time for every transaction, from every entry point (file upload, saved-mapping import, linked-bank sync).
- **FR-003**: Automatic classification MUST use the direction of money (inflow vs outflow), the transaction description/merchant, and the account type. An outflow that is a payment to one of the user's credit accounts or a movement to another of the user's accounts is a **transfer**; every other outflow is an **expense**.
- **FR-004**: Classification of inflows MUST be account-type aware: on a credit account, an inflow that is not a recognized card payment is a **refund**; on any other account, an inflow is a **refund** only when the user has prior expense transactions with the same merchant, and is otherwise **income**.
- **FR-005**: Spending figures — dashboard totals, category breakdown, monthly trend, budgets, largest/recent spending, merchant totals — MUST include expenses and MUST exclude income and transfers.
- **FR-006**: Refunds MUST reduce spending in the category and merchant they belong to, rather than being ignored.
- **FR-007**: Transfers MUST be excluded from income as well as from spending, on both the sending and receiving side.
- **FR-008**: When both legs of a transfer are present, the system MUST recognize them as one movement and MUST NOT count either leg as spending or income. Two transactions pair only when they have the same absolute amount, opposite directions, the same currency, belong to two different accounts of the same user, and post within 3 days of each other.
- **FR-009**: Users MUST be able to change the flow type of any individual transaction.
- **FR-010**: Changing a transaction's flow type MUST also create or update a reusable rule keyed on that transaction's merchant, so subsequently imported rows resolving to the same merchant receive the same flow type. Users MUST still be able to override an individual transaction without changing the rule.
- **FR-011**: A user-set flow type (whether on the transaction or via its rule) MUST take precedence over automatic classification and MUST survive re-import and re-sync.
- **FR-012**: The transactions list MUST display each transaction's flow type and MUST allow filtering by flow type.
- **FR-013**: The dashboard MUST report, for the selected period, total income, total spending, and net cash flow (income minus spending). Income is reported as a single total and is not broken down by category.
- **FR-014**: Categories and budgets MUST remain expense-only; income and transfers MUST NOT roll up to categories or consume budget.
- **FR-015**: Transfer and income transactions MUST still resolve to a merchant so that rules can key on it, but the Merchants page MUST list only merchants with expense activity by default, with the remainder reachable through a filter.
- **FR-016**: Existing transactions MUST be classified retroactively when the feature is introduced, so previously imported data becomes correct without user action.
- **FR-017**: Transactions of any flow type MUST remain visible and searchable in the transactions list — nothing is hidden, only excluded from spending math.
- **FR-018**: Amount presentation MUST make direction obvious (money in vs money out) rather than relying on a bare sign.

### Key Entities

- **Transaction**: gains a **flow type** (expense, income, transfer, refund) and a record of whether that type was set automatically or by the user. Its existing attributes (account, merchant, amount, date, description) are unchanged.
- **Transfer link**: the association between two transactions that are the two legs of the same movement between the user's own accounts. Each transaction belongs to at most one link.
- **Classification rule**: per-user, per-merchant knowledge of which flow type a merchant's transactions take. Created or updated whenever the user corrects a transaction, and consulted ahead of automatic classification on every subsequent import. Existing per-merchant rules are the natural home for this.
- **Account**: its type (checking, savings, credit, cash, investment) informs classification (e.g. an outflow from checking to a credit account is a card payment).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For a checking-account statement containing deposits, transfers, card payments, and purchases, reported spending for the period equals the sum of the purchases alone — 100% agreement with a manual tally, with no double-counting of credit-card purchases.
- **SC-002**: At least 90% of rows in a typical checking-account statement are classified correctly without user intervention.
- **SC-003**: A user can correct a misclassified transaction in under 10 seconds, and every affected total updates on the next view with no re-import.
- **SC-007**: A correction made once is never needed twice: after correcting a recurring statement row (e.g. a monthly paycheck or savings transfer), the next import of the same row requires zero further intervention.
- **SC-004**: 100% of transactions in the system have a flow type, including all transactions imported before this feature.
- **SC-005**: No transfer between two accounts the user tracks contributes to spending or income totals, on either leg.
- **SC-006**: A user can answer "how much came in, how much went out, and what's left" for any period from a single dashboard view.

## Assumptions

- Positive amounts represent outflows (money out) and negative amounts represent inflows, matching how transactions are stored today; the feature does not change the storage sign convention.
- Only the four flow types above are needed; investment buys/sells, fees, and interest are treated as expense or income and are not modeled separately in this feature.
- Transfer pairing is limited to two different accounts belonging to the same user, matching an equal amount in the opposite direction, in the same currency, within ±3 days; anything looser is not auto-paired.
- Budgets remain expense-only. Income budgets/targets and income categories are out of scope.
- Retroactive classification uses the same automatic rules as import; the user may correct any result afterwards.
- Multi-currency handling is unchanged; transfer pairing assumes both legs share a currency.
- Per-user data isolation, the existing period selector, and existing import/dedup behavior are reused as-is.
