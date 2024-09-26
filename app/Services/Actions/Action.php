<?php

namespace App\Services\Actions;

use App\Models\Transaction;

class Action
{
    public function createModel(string $className, array $data)
    {
        return $className::create($data);
    }
}
