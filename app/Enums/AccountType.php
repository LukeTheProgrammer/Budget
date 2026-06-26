<?php

namespace App\Enums;

/**
 * The kind of account a user is tracking. Optional on an account; manual
 * accounts choose from this fixed set, linked accounts derive their type from
 * the financial institution.
 */
enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Credit = 'credit';
    case Cash = 'cash';
    case Investment = 'investment';

    /**
     * A human-readable label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Checking',
            self::Savings => 'Savings',
            self::Credit => 'Credit',
            self::Cash => 'Cash',
            self::Investment => 'Investment',
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
