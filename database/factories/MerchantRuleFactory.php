<?php

namespace Database\Factories;

use App\Enums\MerchantRuleType;
use App\Models\Merchant;
use App\Models\MerchantRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantRule>
 */
class MerchantRuleFactory extends Factory
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
            'merchant_id' => Merchant::factory(),
            'match_type' => MerchantRuleType::Prefix,
            'pattern' => fake()->company(),
            'priority' => 0,
        ];
    }

    /**
     * A regex matching rule.
     */
    public function regex(string $pattern): static
    {
        return $this->state(fn (): array => [
            'match_type' => MerchantRuleType::Regex,
            'pattern' => $pattern,
        ]);
    }
}
