<?php

namespace App\Enums;

enum TaxonomyType: string
{
    case Category = 'category';
    case Tag = 'tag';
    case Campaign = 'campaign';
    case Country = 'country';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
