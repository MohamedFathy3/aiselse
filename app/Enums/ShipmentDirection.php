<?php

namespace App\Enums;

enum ShipmentDirection: string
{
    case Import = 'import';
    case Export = 'export';
    case Domestic = 'domestic';
    case CrossBooking = 'cross_booking';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
