<?php

namespace App\Enums;

enum FollowUpType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case WhatsApp = 'whatsapp';
    case General = 'general';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
