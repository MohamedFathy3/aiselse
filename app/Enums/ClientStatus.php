<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnHold = 'on_hold';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
