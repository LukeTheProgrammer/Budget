<?php

namespace Tests\Feature\Budgets;

use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_recalculate_budgets(): void
    {
        $this->post(route('budgets.recalculate'))->assertRedirect(route('login'));
    }

    public function test_budgets_are_set_to_the_rounded_up_monthly_average_spend(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'monthly_budget_cents' => 99]);
        $merchant = Merchant::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $this->spend($account, $merchant, 30000, '2026-08-05');
        $this->spend($account, $merchant, 5000, '2026-08-20');
        $this->spend($account, $merchant, 20550, '2026-09-10');
        $this->spend($account, $merchant, -550, '2026-09-12', 'refund');
        $this->spend($account, $merchant, 90000, '2026-09-15', 'transfer');

        $this->actingAs($user)
            ->post(route('budgets.recalculate'))
            ->assertRedirect();

        // (35000 + 20000) / 2 months = 27500, rounded up to 28000.
        $this->assertSame(28000, $category->fresh()->monthly_budget_cents);
    }

    public function test_categories_without_spending_get_a_zero_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id, 'monthly_budget_cents' => 5000]);

        $this->actingAs($user)->post(route('budgets.recalculate'));

        $this->assertSame(0, $category->fresh()->monthly_budget_cents);
    }

    public function test_other_users_budgets_are_left_untouched(): void
    {
        $user = User::factory()->create();
        $otherCategory = Category::factory()->create(['monthly_budget_cents' => 5000]);

        $this->actingAs($user)->post(route('budgets.recalculate'));

        $this->assertSame(5000, $otherCategory->fresh()->monthly_budget_cents);
    }

    private function spend(Account $account, Merchant $merchant, int $amountCents, string $postedAt, string $flowType = 'expense'): void
    {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_id' => $merchant->id,
            'amount_cents' => $amountCents,
            'posted_at' => $postedAt,
            'flow_type' => $flowType,
        ]);
    }
}
