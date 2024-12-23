<?php

use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VendorAliasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vendor-aliases/{vendor_alias}/remove-vendor', [VendorController::class, 'removeVendor']);
    Route::resource('vendors', VendorController::class);
    Route::resource('vendor-aliases', VendorAliasController::class);
});
