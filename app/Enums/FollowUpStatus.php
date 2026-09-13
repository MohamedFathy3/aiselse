<?php

namespace App\Enums;

enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Overdue = 'overdue';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
