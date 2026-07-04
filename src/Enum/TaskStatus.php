<?php

namespace App\Enum;

enum TaskStatus: string 
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}