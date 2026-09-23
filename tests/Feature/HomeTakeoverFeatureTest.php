<?php

namespace Tests\Feature;

use App\Enums\EditorialStatus;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use App\Services\TakeoverPresaleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTakeoverFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 23));
    }

    public function test_migration_features_the_existing_takeover_event_for_guests_and_members(): void
    {
        $event = app(TakeoverPresaleSeeder::class)->seed();
        $eventBefore = $event->fresh()->getAttributes();

        // Warm the old homepage payload before the selection changes.
        $this->get('/')->assertOk()->assertDontSee('Reny Renteria Takeover');
        $this->migration()->up();

        $this->assertSame($eventBefore, $event->fresh()->getAttributes());
        $this->assertHomeFeaturesTakeover();
        $this->actingAs(User::factory()->create());
        $this->assertHomeFeaturesTakeover();

        $this->getJson(route('public-content.payload', 'home'))
            ->assertOk()
            ->assertJsonPath('events.0.content_id', $event->id)
            ->assertJsonPath('events.0.product_key', TakeoverPresaleSeeder::KEY)
            ->assertJsonPath('events.0.price_label', '$20')
            ->assertJsonMissingPath('events.0.inventory');
        $this->get('/store/checkout/'.TakeoverPresaleSeeder::KEY)
            ->assertOk()->assertSee('Presale [Limited]')->assertSee('$20');
    }

    public function test_migration_preserves_other_settings_drafts_and_event_edits(): void
    {
        $event = app(TakeoverPresaleSeeder::class)->seed();
        $event->update(['metadata' => [...$event->metadata, 'price_cents' => 2500]]);
        $payload = app(StorefrontSettingsService::class)->defaults();
        $payload['royal_pass']['copy_before'] = 'Custom Royal Pass copy';
        $payload['slots']['album']['title'] = 'Custom Album';
        $payload['slots']['event_primary']['image_url'] = 'https://example.test/old-poster.png';

        foreach ([SitePageSetting::STATUS_PUBLISHED, SitePageSetting::STATUS_DRAFT] as $status) {
            SitePageSetting::create([
                'page' => 'store', 'section' => 'storefront', 'status' => $status,
                'payload' => $payload,
            ]);
        }

        $this->migration()->up();
        $published = app(StorefrontSettingsService::class)->publishedSetting();
        $after = $published->getAttributes();
        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame($after, $published->fresh()->getAttributes());
        $this->assertSame($payload, app(StorefrontSettingsService::class)->draftSetting()->payload);
        foreach (['album', 'merch', 'event_secondary'] as $slot) {
            $this->assertSame($payload['slots'][$slot], $published->payload['slots'][$slot]);
        }
        $this->assertSame($payload['royal_pass'], $published->payload['royal_pass']);
        $this->get('/')->assertOk()->assertSee('$25')->assertSee(TakeoverPresaleSeeder::POSTER)
            ->assertDontSee('old-poster.png')->assertDontSee('data-free-event-rsvp="', false);
    }

    public function test_feature_respects_cms_unpublishing_and_event_expiration(): void
    {
        $event = app(TakeoverPresaleSeeder::class)->seed();
        $this->migration()->up();
        $this->assertHomeFeaturesTakeover();

        $event->update(['status' => EditorialStatus::Draft]);
        $this->get('/')->assertOk()->assertDontSee('Reny Renteria Takeover');

        $event->update(['status' => EditorialStatus::Published]);
        $this->travelTo(now()->setDate(2027, 10, 3));
        $this->get('/')->assertOk()->assertDontSee('class="home-show-card"', false);
    }

    public function test_migration_does_not_recreate_a_removed_event(): void
    {
        $this->migration()->up();

        $this->assertDatabaseCount('editorial_contents', 0);
        $this->assertNull(app(StorefrontSettingsService::class)->publishedSetting());
    }

    private function assertHomeFeaturesTakeover(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('Reny Renteria Takeover')
            ->assertSee('Presale [Limited]')
            ->assertSee('Rock &amp; Folk Pty', false)
            ->assertSee('02/ oct. - 8:30 p. m.')
            ->assertSee('Puertas 7:30 PM · Show 8:30 PM')
            ->assertSee('$20')
            ->assertSee(TakeoverPresaleSeeder::POSTER)
            ->assertSee('data-countdown-at="2027-10-02T20:30:00-05:00"', false)
            ->assertSee('data-buy="'.TakeoverPresaleSeeder::KEY.'"', false)
            ->assertSee('data-buy-url="'.route('store.checkout', ['product' => TakeoverPresaleSeeder::KEY]).'"', false)
            ->assertDontSee('data-free-event-rsvp="', false)
            ->assertDontSee('Festival de la Rosa Dorada')
            ->assertDontSee('400 tickets')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="home-show-card"'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_23_070000_feature_takeover_on_home.php');
    }
}
