<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Budgets\BudgetRecalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Set each category's budget to the monthly average spend on that category
     * for every user. See BudgetRecalculator for the derivation.
     */
    public function run(BudgetRecalculator $recalculator): void
    {
        User::query()->each(fn (User $user) => $recalculator->recalculateForUser($user->id));
    }
}
