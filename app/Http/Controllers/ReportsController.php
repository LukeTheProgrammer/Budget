<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    /**
     * Provision a new web server.
     */
    public function __invoke()
    {
        return Inertia::render('Reports', [
            'transactions' => Transaction::all(),
        ]);
    }
}
