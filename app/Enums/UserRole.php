<?php

namespace App\Enums;

/**
 * System user roles.
 *
 * IMPORTANT: This is intentionally a small, closed set for the MVP, but the
 * authorization layer (Policies + EnsureUserHasRole middleware) is written
 * so new roles can be appended here without touching business logic elsewhere.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
