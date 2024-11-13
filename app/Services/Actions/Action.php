<?php

namespace App\Services\Actions;

use App\Models\Transaction;
use App\Models\Vendor;
use App\Models\VendorAlias;

class Action
{
    public function createModel(string $className, array $data)
    {
        return $className::create($data);
    }

    public function resolveVendor(string $name): Vendor
    {
        $alias = VendorAlias::firstOrCreate([
            'name' => $name,
        ]);

        if ($alias->vendor instanceof Vendor) {
            return $alias->vendor;
        }

        $vendor = Vendor::firstOrCreate([
            'name' => $name,
        ]);

        $alias->update([
            'vendor_id' => $vendor->id,
        ]);

        return $vendor;
    }
}
