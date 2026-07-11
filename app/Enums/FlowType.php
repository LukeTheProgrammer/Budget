<?php

namespace App\Enums;

/**
 * The kind of money movement a transaction represents. Every transaction has
 * exactly one, assigned automatically at import and correctable by the user.
 *
 * The sign convention is unchanged: positive amounts are outflows, negative
 * amounts are inflows. Expenses are therefore positive and refunds negative,
 * which is what lets a single SUM over {@see self::spendingCases()} net refunds
 * out of a category, merchant, or budget total.
 */
enum FlowType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Refund = 'refund';

    /**
     * The flow types that count toward spending. Income and transfers are money
     * that was never spent, and are excluded from every spending figure.
     *
     * @return list<self>
     */
    public static function spendingCases(): array
    {
        return [self::Expense, self::Refund];
    }

    /**
     * The values of {@see self::spendingCases()}, for use in query builders.
     *
     * @return list<string>
     */
    public static function spendingValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::spendingCases());
    }

    /**
     * A human-readable label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Expense',
            self::Income => 'Income',
            self::Transfer => 'Transfer',
            self::Refund => 'Refund',
        };
    }

    /**
     * The set of types as value/label option pairs for select inputs.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
