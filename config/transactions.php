<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Import Account
    |--------------------------------------------------------------------------
    |
    | All transactions imported from CSV files are associated with this single
    | account for v1. Set the value to the id of an existing `accounts` record
    | owned by your user. The importer aborts with a clear error when this is
    | not configured.
    |
    */

    'default_account_id' => env('TRANSACTIONS_DEFAULT_ACCOUNT_ID'),

];
