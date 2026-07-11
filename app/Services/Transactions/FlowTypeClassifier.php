<?php

namespace App\Services\Transactions;

use App\Enums\AccountType;
use App\Enums\FlowType;
use App\Models\Account;
use App\Models\Merchant;
use App\Models\Transaction;

/**
 * Decides what kind of money movement a transaction represents: an expense, a
 * refund, income, or a transfer between the user's own accounts. This is the
 * single home of that rule — every import entry point (file upload, mapped
 * import, Plaid sync, retroactive backfill) classifies through it, so a
 * statement classifies identically no matter how it arrived.
 *
 * Prime once per user with {@see forUser()} before classifying a batch, so the
 * "have they spent here before?" test is answered from memory rather than a
 * query per row.
 */
class FlowTypeClassifier
{
    /**
     * Descriptors that mark money moving between accounts rather than being
     * earned or spent: internal transfers, card payments, and the bill-pay
     * rails. Matched case-insensitively as substrings of the raw description.
     *
     * @var list<string>
     */
    private const TRANSFER_DESCRIPTORS = [
        'transfer',
        'xfer',
        'payment thank you',
        'autopay',
        'auto pay',
        'epay',
        'e-payment',
        'bill pay',
        'billpay',
        'online payment',
        'card payment',
        'zelle',
        'venmo',
        'withdrawal to',
        'deposit from checking',
        'deposit from savings',
    ];

    private ?int $userId = null;

    /**
     * Ids of merchants the user has at least one expense with. Used to tell a
     * store refund apart from income: an inflow from somewhere you have spent
     * money is a return, an inflow from somewhere you never have is income.
     *
     * @var array<int, true>
     */
    private array $merchantsWithExpenses = [];

    /**
     * Prime (or re-prime) the classifier for a user. Returns $this for fluent
     * use at the start of an import run.
     *
     * The retroactive backfill passes $primeFromExisting = false: every row
     * starts life at the column's `expense` default, so the stored flow types
     * are not yet evidence of anything. It instead walks the user's history in
     * date order and lets the merchant-expense set build up as it goes, which
     * is what "has the user spent here *before*?" means anyway.
     */
    public function forUser(int $userId, bool $primeFromExisting = true): self
    {
        if ($this->userId === $userId && $primeFromExisting) {
            return $this;
        }

        $this->userId = $userId;
        $this->merchantsWithExpenses = [];

        if (! $primeFromExisting) {
            return $this;
        }

        $this->merchantsWithExpenses = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('accounts.user_id', $userId)
            ->where('transactions.flow_type', FlowType::Expense)
            ->whereNotNull('transactions.merchant_id')
            ->distinct()
            ->pluck('transactions.merchant_id')
            ->flip()
            ->map(fn (): bool => true)
            ->all();

        return $this;
    }

    /**
     * Classify one transaction.
     *
     * Precedence: the merchant's user-taught default wins outright; otherwise
     * the direction of the money, the description, and the account type decide.
     * A positive amount is an outflow, a negative amount an inflow.
     */
    public function classify(Account $account, Merchant $merchant, int $amountCents, ?string $description): FlowType
    {
        if ($merchant->default_flow_type !== null) {
            return $merchant->default_flow_type;
        }

        $isTransferDescriptor = $this->matchesTransferDescriptor($description)
            || $this->matchesTransferDescriptor($merchant->name);

        if ($amountCents >= 0) {
            $flowType = $isTransferDescriptor ? FlowType::Transfer : FlowType::Expense;
        } elseif ($isTransferDescriptor) {
            $flowType = FlowType::Transfer;
        } elseif ($account->type === AccountType::Credit) {
            // An inflow to a card that is not the card payment is a merchant
            // credit — there is nothing else it can be.
            $flowType = FlowType::Refund;
        } elseif (isset($this->merchantsWithExpenses[$merchant->id])) {
            $flowType = FlowType::Refund;
        } else {
            $flowType = FlowType::Income;
        }

        $this->remember($merchant->id, $flowType);

        return $flowType;
    }

    /**
     * Track a merchant that has just gained its first expense, so rows later in
     * the same batch can see it without re-querying.
     */
    private function remember(int $merchantId, FlowType $flowType): void
    {
        if ($flowType === FlowType::Expense) {
            $this->merchantsWithExpenses[$merchantId] = true;
        }
    }

    /**
     * Whether a raw descriptor names money moving between accounts.
     */
    private function matchesTransferDescriptor(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $haystack = mb_strtolower($value);

        foreach (self::TRANSFER_DESCRIPTORS as $descriptor) {
            if (str_contains($haystack, $descriptor)) {
                return true;
            }
        }

        return false;
    }
}
