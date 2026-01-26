<?php

namespace App\Enums;

enum MatchingStatus: int
{
    case PENDING = 1;
    case ACCEPTED = 2;
    case REJECTED = 3;
    case DEAL_DONE = 4;
}
