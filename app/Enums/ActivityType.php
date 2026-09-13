<?php

namespace App\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case WhatsApp = 'whatsapp';
    case Note = 'note';
    case FollowUp = 'follow_up';
    case StatusChange = 'status_change';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
