<?php

namespace App\Enums;

/**
 * Who decided a transaction's {@see FlowType}. A user-set flow type is never
 * overwritten by automatic classification, so a correction survives re-import
 * and re-sync.
 */
enum FlowTypeSource: string
{
    case Auto = 'auto';
    case User = 'user';
}
