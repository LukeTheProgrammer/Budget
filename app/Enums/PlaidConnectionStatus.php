<?php

namespace App\Enums;

/**
 * Health of a Plaid connection (Item) as surfaced to the user.
 */
enum PlaidConnectionStatus: string
{
    case Active = 'active';
    case ReauthRequired = 'reauth_required';
    case Error = 'error';
}
