<?php

namespace App\Enums;

/**
 * A Contact belongs to either a Lead or a Client (polymorphic), never both.
 */
enum ContactableType: string
{
    case Lead = 'lead';
    case Client = 'client';
}
