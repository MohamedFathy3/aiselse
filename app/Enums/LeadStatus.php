<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Interested = 'interested';
    case FollowUp = 'follow_up';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case NotInterested = 'not_interested';
    case Lost = 'lost';

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /**
     * Statuses a Lead can still be edited/contacted in.
     * Converted leads become read-mostly; the Client record takes over.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::NotInterested, self::Lost], true);
    }
}
