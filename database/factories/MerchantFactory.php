<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
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
            'name' => fake()->company(),
        ];
    }

    /**
     * Every merchant owns a self-alias of its own name so it stays resolvable
     * via the alias table (the sole matching key). Runs even under
     * WithoutModelEvents, since factory callbacks are not model events.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Merchant $merchant): void {
            MerchantAlias::firstOrCreate(
                ['user_id' => $merchant->user_id, 'normalized_name' => mb_strtolower(trim($merchant->name))],
                ['merchant_id' => $merchant->id, 'name' => $merchant->name],
            );
        });
    }
}
