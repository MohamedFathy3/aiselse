<?php

namespace App\Enums;

enum LeadSearchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
