<?php

namespace App\Enums;

enum TransportType: string
{
    case Ocean = 'ocean';
    case Air = 'air';
    case Inland = 'inland';
    case CustomsClearance = 'customs_clearance';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
