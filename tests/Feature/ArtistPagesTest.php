<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bio_and_contact_are_public_and_have_the_supplied_content(): void
    {
        $this->get('/bio')->assertOk()
            ->assertSee('9 de octubre de 1998')
            ->assertSee('Canta Conmigo')
            ->assertSee('In The Heights, El Musical')
            ->assertSee('Miss Universe Panamá en 2024 y 2026')
            ->assertSee('Work in Progress')
            ->assertSee('Take a bite');

        $this->get('/contacto')->assertOk()
            ->assertSee('Contrataciones')
            ->assertSee('https://www.instagram.com/renyrenteria/', false)
            ->assertDontSee('<form', false);
    }

    public function test_merch_shows_published_physical_products_but_not_music_events_or_drafts(): void
    {
        foreach ([
            ['Official tour shirt', 'physical', EditorialStatus::Published],
            ['Download album', 'digital', EditorialStatus::Published],
            ['Unannounced shirt', 'physical', EditorialStatus::Draft],
        ] as [$title, $kind, $status]) {
            EditorialContent::query()->create([
                'type' => ContentType::Product,
                'title' => $title,
                'slug' => str($title)->slug()->toString(),
                'summary' => 'Catalog test item',
                'status' => $status,
                'visibility' => VisibilityAudience::Open,
                'published_at' => $status === EditorialStatus::Published ? now()->subMinute() : null,
                'metadata' => [
                    'product_kind' => $kind,
                    'action_type' => 'link',
                    'action_url' => 'https://example.com/product',
                ],
            ]);
        }

        $this->get('/merch')->assertOk()
            ->assertSee('Official tour shirt')
            ->assertSee('Crown Collection')
            ->assertDontSee('Download album')
            ->assertDontSee('Unannounced shirt')
            ->assertDontSee('Reny Renteria en Concierto')
            ->assertDontSee('class="home-show-card"', false)
            ->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'merch']).'"', false);
    }
}
