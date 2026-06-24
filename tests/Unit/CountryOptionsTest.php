<?php

namespace Tests\Unit;

use App\Support\CountryOptions;
use PHPUnit\Framework\TestCase;

class CountryOptionsTest extends TestCase
{
    public function test_country_options_keep_priority_markets_and_fallback(): void
    {
        $countries = CountryOptions::names();

        $this->assertSame('Panama', $countries[0]);
        $this->assertContains('United States', $countries);
        $this->assertContains('Colombia', $countries);
        $this->assertSame('Other', $countries[array_key_last($countries)]);
        $this->assertSame($countries, array_values(array_unique($countries)));
    }
}
