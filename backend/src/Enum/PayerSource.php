<?php

namespace App\Enum;

enum PayerSource: string
{
    case SELF = 'SELF';
    case MORTGAGE_DEPOT = 'MORTGAGE_DEPOT';
    case INSURER = 'INSURER';
    case OTHER = 'OTHER';
}
