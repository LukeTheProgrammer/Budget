<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reset-data')]
#[Description('Reset database')]
class ResetDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Migrating');
        $this->call('migrate:fresh', ['--seed' => true]);

        $this->info('Importing');
        $this->call('transactions:import', ['--all' => true]);

        $this->info('Seeding');
        $this->call('db:seed', ['--class' => 'BudgetSeeder']);
    }
}
