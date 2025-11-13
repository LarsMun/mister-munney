<?php

namespace App\Enum;

/**
 * Status of account user relationship
 *
 * ACTIVE: User has active access to the account
 * PENDING: Invitation sent, awaiting user acceptance
 * REVOKED: Access has been revoked by owner
 */
enum AccountUserStatus: string
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case REVOKED = 'revoked';
}
