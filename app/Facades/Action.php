<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Action extends Facade
{
    /**
     * @inheritdoc
     */
    protected static function getFacadeAccessor()
    {
        return 'action';
    }
}
