<?php

namespace App\Enums;

enum PhotoStatus: string
{
    case Processing = 'processing';
    case Active = 'active';
    case Archived = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
