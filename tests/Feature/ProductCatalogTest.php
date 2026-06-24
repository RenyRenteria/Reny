<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Services\Commerce\ProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_products_are_resolved_from_versioned_config(): void
    {
        config()->set('reny_catalog.products', [
            'zine' => [
                'title' => 'Digital Tour Zine',
                'amount_cents' => 1200,
                'kind' => 'digital',
                'unlock_type' => 'album',
            ],
        ]);

        $product = app(ProductCatalog::class)->find('zine');

        $this->assertSame('zine', $product['key']);
        $this->assertSame('Digital Tour Zine', $product['title']);
        $this->assertSame(1200, $product['amount_cents']);
        $this->assertSame('USD', $product['currency']);
        $this->assertSame('digital', $product['kind']);
        $this->assertSame('album', $product['unlock_type']);
        $this->assertSame('order', $product['source_type']);
    }

    public function test_configured_ticket_products_preserve_event_payload(): void
    {
        config()->set('reny_catalog.products', [
            'config-ticket' => [
                'title' => 'Config Ticket',
                'amount_cents' => 3300,
                'kind' => 'ticket',
                'unlock_type' => null,
                'event' => [
                    'title' => 'Config Ticket',
                    'venue' => 'Config Venue',
                    'address' => 'Config Address',
                    'starts_at' => '2026-10-12 20:00:00',
                    'timezone' => 'America/Panama',
                ],
            ],
        ]);

        $product = app(ProductCatalog::class)->find('config-ticket');

        $this->assertSame('ticket', $product['kind']);
        $this->assertSame('Config Venue', $product['event']['venue']);
        $this->assertSame('2026-10-12 20:00:00', $product['event']['starts_at']);
    }

    public function test_cms_product_takes_precedence_over_configured_product_with_same_key(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'CMS Deluxe Override',
            'slug' => 'cms-deluxe-override',
            'purchase_key' => 'deluxe',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 3100,
            ],
        ]);

        $product = app(ProductCatalog::class)->find('deluxe');

        $this->assertSame('CMS Deluxe Override', $product['title']);
        $this->assertSame(3100, $product['amount_cents']);
        $this->assertSame('editorial_content', $product['source_type']);
    }

    public function test_cms_rsvp_event_with_configured_key_blocks_checkout_fallback(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'CMS RSVP Concert',
            'slug' => 'cms-rsvp-concert',
            'purchase_key' => 'concert',
            'metadata' => [
                'ticketing_mode' => 'rsvp',
                'starts_at' => '2026-10-01 19:00:00',
                'location' => 'CMS Venue',
                'price_cents' => 0,
            ],
        ]);

        $this->assertNull(app(ProductCatalog::class)->find('concert'));
    }

    public function test_empty_or_invalid_product_config_returns_null_without_throwing(): void
    {
        config()->set('reny_catalog.products', [
            'missing-title' => [
                'amount_cents' => 1200,
                'kind' => 'digital',
            ],
            'free' => [
                'title' => 'Free Product',
                'amount_cents' => 0,
                'kind' => 'digital',
            ],
            'missing-kind' => [
                'title' => 'Missing Kind',
                'amount_cents' => 1200,
            ],
        ]);

        $catalog = app(ProductCatalog::class);

        $this->assertNull($catalog->find('missing-title'));
        $this->assertNull($catalog->find('free'));
        $this->assertNull($catalog->find('missing-kind'));

        config()->set('reny_catalog.products', []);

        $this->assertNull($catalog->find('deluxe'));
    }
}
