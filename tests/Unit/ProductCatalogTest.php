<?php

namespace Tests\Unit;

use App\Services\Commerce\ProductCatalog;
use PHPUnit\Framework\TestCase;

class ProductCatalogTest extends TestCase
{
    public function test_static_royal_pass_product_has_checkout_contract(): void
    {
        $product = (new ProductCatalog)->find('royal');

        $this->assertSame([
            'key' => 'royal',
            'title' => 'Royal Pass',
            'amount_cents' => 499,
            'currency' => 'USD',
            'kind' => 'subscription',
            'unlock_type' => null,
            'source_type' => 'order',
            'event' => null,
        ], $product);
    }

    public function test_static_event_product_keeps_event_payload(): void
    {
        $product = (new ProductCatalog)->find('concert');

        $this->assertSame('concert', $product['key']);
        $this->assertSame('ticket', $product['kind']);
        $this->assertSame('Reny Live - Studio Night', $product['event']['title']);
        $this->assertSame('America/Panama', $product['event']['timezone']);
    }
}
