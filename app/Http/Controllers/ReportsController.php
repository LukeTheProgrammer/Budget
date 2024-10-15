<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportDataResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    /**
     * Provision a new web server.
     */
    public function index()
    {
        return Inertia::render('Reports', [
            'transactions' => Transaction::all(),
        ]);
    }

    public function getData()
    {
        return response()->json(
            new ReportDataResource(Transaction::all())
        );
    }
}
