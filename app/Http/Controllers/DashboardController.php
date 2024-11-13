<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Vendor;
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
            'transactions' => Transaction::query()->with(['vendor'])->get(),
            'vendors' => Vendor::query()->orderBy('name')->get(),
        ]);
    }
}
