<?php

namespace App\Enums;

enum PhotoVisibility: string
{
    case Public = 'public';
    case MemberOnly = 'member_only';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $visibility): string => $visibility->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::MemberOnly => 'Member only',
        };
    }
}
