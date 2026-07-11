<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\Merchants\MerchantAliasController;
use App\Http\Controllers\Merchants\MerchantController;
use App\Http\Controllers\Merchants\MerchantDefaultTagController;
use App\Http\Controllers\Merchants\MerchantGroupController;
use App\Http\Controllers\Merchants\MerchantRuleController;
use App\Http\Controllers\Plaid\PlaidConnectionController;
use App\Http\Controllers\Plaid\PlaidLinkController;
use App\Http\Controllers\Plaid\PlaidLinkTokenController;
use App\Http\Controllers\Plaid\PlaidSyncController;
use App\Http\Controllers\SessionPeriodController;
use App\Http\Controllers\Tags\TagController;
use App\Http\Controllers\Transactions\ImportController;
use App\Http\Controllers\Transactions\TransactionController;
use App\Http\Controllers\Transactions\TransactionFlowTypeController;
use App\Http\Controllers\Transactions\TransactionTagController;
use App\Http\Controllers\Transactions\UploadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('insights', [InsightsController::class, 'index'])->name('insights');

    Route::post('session-period', [SessionPeriodController::class, 'update'])->name('session-period.update');

    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::patch('budgets', [BudgetController::class, 'update'])->name('budgets.update');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions/import', [ImportController::class, 'store'])->name('transactions.import');
    Route::get('transactions/upload', [UploadController::class, 'create'])->name('transactions.upload.create');
    Route::post('transactions/upload', [UploadController::class, 'store'])->name('transactions.upload.store');
    Route::patch('transactions/{transaction}/flow-type', [TransactionFlowTypeController::class, 'update'])->name('transactions.flow-type.update');
    Route::post('transactions/{transaction}/tags', [TransactionTagController::class, 'store'])->name('transactions.tags.store');
    Route::delete('transactions/{transaction}/tags/{tag}', [TransactionTagController::class, 'destroy'])->name('transactions.tags.destroy');

    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    Route::get('merchants', [MerchantController::class, 'index'])->name('merchants.index');
    Route::get('merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show');
    Route::patch('merchants/{merchant}', [MerchantController::class, 'update'])->name('merchants.update');
    Route::post('merchants/group', [MerchantGroupController::class, 'store'])->name('merchants.group');
    Route::post('merchants/{merchant}/aliases', [MerchantAliasController::class, 'store'])->name('merchants.aliases.store');
    Route::delete('merchants/{merchant}/aliases/{alias}', [MerchantAliasController::class, 'destroy'])->name('merchants.aliases.destroy');
    Route::post('merchants/{merchant}/rules', [MerchantRuleController::class, 'store'])->name('merchants.rules.store');
    Route::delete('merchants/{merchant}/rules/{rule}', [MerchantRuleController::class, 'destroy'])->name('merchants.rules.destroy');
    Route::post('merchants/{merchant}/default-tags', [MerchantDefaultTagController::class, 'store'])->name('merchants.default-tags.store');
    Route::delete('merchants/{merchant}/default-tags/{tag}', [MerchantDefaultTagController::class, 'destroy'])->name('merchants.default-tags.destroy');

    // Plaid bank integration.
    Route::prefix('plaid')->name('plaid.')->group(function () {
        Route::post('link-token', [PlaidLinkTokenController::class, 'store'])->name('link-token');
        Route::post('exchange', [PlaidLinkController::class, 'store'])->name('exchange');
        Route::post('connections/{connection}/sync', [PlaidSyncController::class, 'store'])->name('sync');
        Route::delete('connections/{connection}', [PlaidConnectionController::class, 'destroy'])->name('connections.destroy');
    });
});

require __DIR__ . '/settings.php';
