<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->randomElement([
                'Groceries', 'Dining', 'Fuel', 'Utilities', 'Entertainment', 'Travel', 'Health',
            ]),
            'color' => fake()->hexColor(),
            'monthly_budget_cents' => null,
        ];
    }

    /**
     * Give the category a recurring monthly budget.
     */
    public function withBudget(?int $cents = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'monthly_budget_cents' => $cents ?? fake()->numberBetween(10000, 100000),
        ]);
    }
}
