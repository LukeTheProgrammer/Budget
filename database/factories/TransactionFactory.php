<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'merchant_id' => Merchant::factory(),
            'amount_cents' => fake()->numberBetween(100, 50000),
            'currency' => 'USD',
            'description' => fake()->optional()->sentence(3),
            'posted_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'import_hash' => null,
        ];
    }

    /**
     * Indicate the transaction is a refund/credit (negative amount).
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount_cents' => -1 * abs($attributes['amount_cents'] ?? fake()->numberBetween(100, 50000)),
        ]);
    }
}
