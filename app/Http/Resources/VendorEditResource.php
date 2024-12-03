<?php

namespace App\Http\Resources;

use App\Models\VendorAlias;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class VendorEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'aliases' => VendorAlias::orderBy('name')->get(),
            'vendor' => $this->resource->load(['aliases']),
        ];
    }
}
