<?php

namespace App\Enums;

enum ShipmentType: string
{
    case FCL = 'fcl';
    case LCL = 'lcl';
    case Bulk = 'bulk';
    case Flexi = 'flexi';
    case Tank = 'tank';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
