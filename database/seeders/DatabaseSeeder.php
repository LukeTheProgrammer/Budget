<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Luke Henry',
            'email' => 'luke@authentech-software.com',
            'password' => Hash::make('Mullen@3'),
        ]);

        $account = Account::factory()->for($user)->create([
            'name' => 'Chase CC',
            'last_four' => '7452',
        ]);

        $this->call(MerchantRuleSeeder::class);
    }
}
