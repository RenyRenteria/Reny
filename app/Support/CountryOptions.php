<?php

namespace App\Support;

class CountryOptions
{
    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            'Panama',
            'Dominican Republic',
            'United States',
            'Puerto Rico',
            'Mexico',
            'Colombia',
            'Costa Rica',
            'Venezuela',
            'Spain',
            'Other',
        ];
    }
}
