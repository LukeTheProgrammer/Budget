<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Controller;
use App\Services\Budgets\BudgetRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetRecalculationController extends Controller
{
    /**
     * Recalculate every one of the user's category budgets from their
     * spending history, overwriting any manually set amounts.
     */
    public function store(Request $request, BudgetRecalculator $recalculator): RedirectResponse
    {
        $recalculator->recalculateForUser($request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budgets recalculated.')]);

        return back();
    }
}
