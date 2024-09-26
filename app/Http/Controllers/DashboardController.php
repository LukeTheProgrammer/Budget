<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Provision a new web server.
     */
    public function __invoke()
    {
        return Inertia::render('Dashboard', [
            'transactions' => Transaction::all(),
        ]);
    }
}
