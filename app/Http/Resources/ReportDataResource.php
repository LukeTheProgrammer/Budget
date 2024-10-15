<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ReportDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catagories = DB::table('transactions')
            ->select('category')
            ->where('category', '<>', '')
            ->distinct()
            ->pluck('category')
            ->all();

        $data = [];

        $this->resource->each(function ($t) use (&$data, $catagories) {
            if (!in_array($t->category, $catagories)) {
                return true;
            }

            if (!isset($data[$t->category])) {
                $data[$t->category] = 0;
            }

            $data[$t->category] += $t->amount;
        });

        return [
            'catagories' => $catagories,
            'chartData' => $data,
            // 'transactions' => parent::toArray($request),
        ];
    }
}
