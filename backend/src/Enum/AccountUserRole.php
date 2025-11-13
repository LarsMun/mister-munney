<?php

namespace App\Enum;

/**
 * Roles for account access
 *
 * OWNER: Full admin control over account (can invite/revoke users)
 * SHARED: Read/write access to account transactions (cannot manage users)
 */
enum AccountUserRole: string
{
    case OWNER = 'owner';
    case SHARED = 'shared';
}
