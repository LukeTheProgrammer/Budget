<?php

namespace App\Http\Controllers\Api;

use App\Models\VendorAlias;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVendorAliasRequest;
use App\Http\Resources\VendorAliasEditResource;
use Illuminate\Http\Request;

class VendorAliasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(VendorAlias $vendorAlias)
    {
        return response()->json($vendorAlias->load(['vendor']));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VendorAlias $vendorAlias)
    {
        return response()->json(new VendorAliasEditResource($vendorAlias));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorAliasRequest $request, VendorAlias $vendorAlias)
    {
        $vendorAlias->update($request->validated());

        return response()->json($vendorAlias->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VendorAlias $vendorAlias)
    {
        //
    }

    public function removeAlias(VendorAlias $vendorAlias)
    {
        // dd([
        //     'vendor' => $vendor,
        //     'alias' => $vendorAlias,
        // ]);
    }
}
