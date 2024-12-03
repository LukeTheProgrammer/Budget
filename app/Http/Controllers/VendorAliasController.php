<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Vendor;
use App\Models\VendorAlias;
use Inertia\Inertia;

class VendorAliasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('VendorAliases', [
            'vendorAliases' => VendorAlias::query()
                ->with(['vendor'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVendorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(VendorAlias $vendorAlias)
    {
        return response()->json([
            'vendorAlias' => $vendorAlias->load('vendor'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VendorAlias $vendorAlias)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorRequest $request, VendorAlias $vendorAlias)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VendorAlias $vendorAlias)
    {
        //
    }
}
