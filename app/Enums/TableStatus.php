<?php

namespace App\Enums;


enum TableStatus : string
{
    case OPEN = 'OPEN';
    case AVAILABLE = 'AVAILABLE';
    case LOCKED = 'LOCKED';
    case DIRTY = 'DIRTY';
}
