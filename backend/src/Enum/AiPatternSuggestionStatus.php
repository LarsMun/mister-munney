<?php

namespace App\Enum;

enum AiPatternSuggestionStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case ACCEPTED_ALTERED = 'accepted_altered';
    case REJECTED = 'rejected';
}
