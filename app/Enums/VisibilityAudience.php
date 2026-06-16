<?php

namespace App\Enums;

enum VisibilityAudience: string
{
    case Open = 'open';
    case Member = 'member';
    case Royal = 'royal';
    case Purchased = 'purchased';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
