<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\Commerce\ProductCatalog;
use App\Services\StorefrontSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CmsCompletionIssue202Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-13 11:34:00', 'America/Panama'));
        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_video_can_complete_draft_preview_publish_public_and_archive_cycle(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), $this->videoPayload())
            ->assertCreated()
            ->assertJsonPath('status', EditorialStatus::Draft->value);

        $video = EditorialContent::query()->sole();

        $this->get(route('admin.content.preview', ['content' => $video, 'audience' => VisibilityAudience::Purchased->value]))
            ->assertOk()
            ->assertSee('Issue 202 Video')
            ->assertSee('Purchased')
            ->assertSee('Accessible')
            ->assertSee('Audit log')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->patchJson(route('admin.content.update', $video), [
            ...$this->videoPayload(),
            'action' => 'publish',
            'title' => 'Issue 202 Video Published',
        ])
            ->assertOk()
            ->assertJsonPath('status', EditorialStatus::Published->value);

        $video->refresh();

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('music_videos.0.title', 'Issue 202 Video Published')
            ->assertJsonPath('music_videos.0.external_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->get('/videos')
            ->assertOk()
            ->assertSee('Issue 202 Video Published')
            ->assertSee('data-video-player', false);

        $this->post(route('admin.content.archive', $video))->assertRedirect();

        $this->assertSame(EditorialStatus::Archived, $video->fresh()->status);
        $this->assertGreaterThanOrEqual(3, $video->auditLogs()->count());
        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonCount(0, 'music_videos');
        $this->get('/videos')->assertOk()->assertDontSee('Issue 202 Video Published');
    }

    public function test_page_settings_support_draft_preview_publish_and_public_seo(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $draft = $this->pageSettingsPayload([
            'action' => 'draft',
            'title' => 'Draft Video Hub',
            'meta_title' => 'Draft Video SEO',
        ]);

        $this->post(route('admin.site-editor.page-settings.update', ['page' => 'videos']), $draft)
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'videos']));

        $this->assertDatabaseHas('site_page_settings', [
            'page' => 'videos',
            'section' => 'page_settings',
            'status' => SitePageSetting::STATUS_DRAFT,
        ]);
        $this->get('/videos')->assertOk()->assertDontSee('Draft Video Hub');
        $this->get(route('admin.site-editor.preview', ['page' => 'videos', 'audience' => 'member']))
            ->assertOk()
            ->assertSee('Draft Video Hub')
            ->assertSee('Member audience')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $published = $this->pageSettingsPayload([
            'action' => 'publish',
            'title' => 'Published Video Hub',
            'meta_title' => 'Published Video SEO',
        ]);

        $this->post(route('admin.site-editor.page-settings.update', ['page' => 'videos']), $published)
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'videos']));

        $this->assertDatabaseMissing('site_page_settings', [
            'page' => 'videos',
            'section' => 'page_settings',
            'status' => SitePageSetting::STATUS_DRAFT,
        ]);
        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertJsonPath('page.title', 'Published Video Hub')
            ->assertJsonPath('page.meta_title', 'Published Video SEO')
            ->assertJsonPath('page.canonical_url', 'https://renyrenteria.com/videos');
        $this->get('/videos')
            ->assertOk()
            ->assertSee('Published Video Hub')
            ->assertSee('<title>Published Video SEO</title>', false)
            ->assertSee('<link rel="canonical" href="https://renyrenteria.com/videos">', false)
            ->assertSee('<meta property="og:title" content="Issue 202 Videos">', false)
            ->assertSee('<meta name="twitter:title" content="Issue 202 Videos">', false);
    }

    public function test_required_public_pages_share_saved_page_settings_with_their_api_payloads(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        foreach (['videos', 'photos', 'community', 'store'] as $page) {
            $title = 'Issue 202 '.str($page)->headline()->toString().' Page';
            $metaTitle = $title.' SEO';
            $canonical = 'https://renyrenteria.com/'.$page;

            $this->post(route('admin.site-editor.page-settings.update', ['page' => $page]), $this->pageSettingsPayload([
                'title' => $title,
                'meta_title' => $metaTitle,
                'canonical_url' => $canonical,
            ]))->assertRedirect(route('admin.site-editor.show', ['page' => $page]));

            $this->getJson(route('public-content.payload', $page))
                ->assertOk()
                ->assertJsonPath('page.title', $title)
                ->assertJsonPath('page.meta_title', $metaTitle)
                ->assertJsonPath('page.canonical_url', $canonical);
            $this->get('/'.$page)
                ->assertOk()
                ->assertSee($title)
                ->assertSee('<title>'.$metaTitle.'</title>', false)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
        }
    }

    public function test_required_public_pages_expose_real_header_cover_and_seo_editors(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        foreach (['videos' => 'Videos', 'photos' => 'Photos', 'community' => 'Community', 'store' => 'Store'] as $page => $label) {
            $this->get(route('admin.site-editor.show', ['page' => $page]))
                ->assertOk()
                ->assertSee($label.' header and SEO')
                ->assertSee('Open Graph title')
                ->assertSee('Twitter title')
                ->assertDontSee('Falta modelo CMS');
        }
    }

    public function test_store_rejects_unknown_checkout_key_and_inactive_product_publication(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $storefront = app(StorefrontSettingsService::class)->defaults();
        data_set($storefront, 'slots.merch.product_key', 'does-not-exist');

        $this->post(route('admin.site-editor.storefront.update'), [
            'action' => 'publish',
            ...$storefront,
        ])
            ->assertSessionHasErrors('slots.merch.product_key');

        $unsafeStorefront = app(StorefrontSettingsService::class)->defaults();
        data_set($unsafeStorefront, 'slots.merch.action_type', 'link');
        data_set($unsafeStorefront, 'slots.merch.url', '//untrusted.example/path');
        $this->post(route('admin.site-editor.storefront.update'), [
            'action' => 'publish',
            ...$unsafeStorefront,
        ])->assertSessionHasErrors('slots.merch.url');

        $this->postJson(route('admin.content.store'), [
            ...$this->productPayload(),
            'metadata' => [
                ...$this->productPayload()['metadata'],
                'is_active' => false,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.is_active');

        $this->postJson(route('admin.content.store'), [
            ...$this->productPayload(),
            'metadata' => [
                ...$this->productPayload()['metadata'],
                'available_from' => now()->addDay()->toISOString(),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.available_from');

        $this->postJson(route('admin.content.store'), [
            ...$this->productPayload(),
            'metadata' => [
                ...$this->productPayload()['metadata'],
                'action_type' => 'link',
                'action_url' => 'ftp://untrusted.example/file',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.action_url');

        $this->postJson(route('admin.content.store'), [
            ...$this->productPayload(),
            'metadata' => [
                ...$this->productPayload()['metadata'],
                'currency' => 'EUR',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.currency');

        $this->assertDatabaseCount('editorial_contents', 0);
    }

    public function test_storefront_derives_rsvp_action_from_selected_canonical_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'Canonical Free RSVP',
            'purchase_key' => 'canonical-free-rsvp',
            'metadata' => [
                'event_kind' => 'physical',
                'starts_at' => '2026-12-22T19:30:00-05:00',
                'timezone' => 'America/Panama',
                'location' => 'Free Venue',
                'ticketing_mode' => 'rsvp',
                'price_cents' => 0,
                'is_active' => true,
                'action_type' => 'rsvp',
                'cta_label' => 'RESERVE FREE',
            ],
        ]);
        $this->actingAsAdmin($admin);
        $storefront = app(StorefrontSettingsService::class)->defaults();
        data_set($storefront, 'slots.event_primary.content_id', $event->id);
        data_set($storefront, 'slots.event_primary.action_type', 'buy');

        $this->post(route('admin.site-editor.storefront.update'), [
            'action' => 'publish',
            ...$storefront,
        ])->assertRedirect(route('admin.site-editor.show', ['page' => 'store']));

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('storefront.slots.event_primary.title', 'Canonical Free RSVP')
            ->assertJsonPath('storefront.slots.event_primary.action_type', 'rsvp')
            ->assertJsonPath('storefront.slots.event_primary.product_key', 'canonical-free-rsvp')
            ->assertJsonPath('storefront.slots.event_primary.price_label', 'FREE');
        $this->get('/store')
            ->assertOk()
            ->assertSee('Canonical Free RSVP')
            ->assertSee('data-free-event-rsvp="canonical-free-rsvp"', false)
            ->assertDontSee('data-buy="canonical-free-rsvp"', false);
    }

    public function test_canonical_product_drives_store_api_card_and_checkout_then_archives(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), $this->productPayload())
            ->assertCreated()
            ->assertJsonPath('status', EditorialStatus::Published->value);

        $product = EditorialContent::query()->sole();

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('products.0.content_id', $product->id)
            ->assertJsonPath('products.0.key', 'issue-202-product')
            ->assertJsonPath('products.0.amount_cents', 1999)
            ->assertJsonPath('products.0.currency', 'USD')
            ->assertJsonPath('products.0.checkout_url', route('store.checkout', ['product' => 'issue-202-product']));
        $this->get('/store')
            ->assertOk()
            ->assertSee('Issue 202 Product')
            ->assertSee('$19.99')
            ->assertSee('data-buy="issue-202-product"', false)
            ->assertSee('data-buy-price-value="19.99"', false);
        $this->get(route('store.checkout', ['product' => 'issue-202-product']))
            ->assertOk()
            ->assertSee('Issue 202 Product')
            ->assertSee('$19.99')
            ->assertSee('data-product-price-value="19.99"', false);
        $this->get(route('public.content.show', $product))
            ->assertOk()
            ->assertSee('<title>Issue 202 Product SEO</title>', false)
            ->assertSee('<meta property="og:title" content="Issue 202 Product OG">', false);

        $this->post(route('admin.content.archive', $product))->assertRedirect();

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonCount(0, 'products');
        $this->get('/store')->assertOk()->assertDontSee('Issue 202 Product');
        $this->get(route('store.checkout', ['product' => 'issue-202-product']))->assertNotFound();
    }

    public function test_canonical_event_keeps_store_card_and_checkout_date_price_and_currency_identical(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            'action' => 'publish',
            'type' => ContentType::Event->value,
            'title' => 'Issue 202 Canonical Event',
            'summary' => 'One event entity for card and checkout.',
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => 'issue-202-event',
            'metadata' => [
                'event_kind' => 'physical',
                'starts_at' => '2026-12-20T19:30:00-05:00',
                'timezone' => 'America/Panama',
                'location' => 'Issue 202 Venue',
                'ticketing_mode' => 'ticket',
                'price_cents' => 2550,
                'currency' => 'USD',
                'inventory' => 40,
                'checkout_enabled' => true,
                'is_active' => true,
                'action_type' => 'buy',
                'cta_label' => 'GET ISSUE 202 TICKETS',
            ],
        ])->assertCreated();

        $event = EditorialContent::query()->sole();

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('events.0.content_id', $event->id)
            ->assertJsonPath('events.0.key', 'issue-202-event')
            ->assertJsonPath('events.0.starts_at', '2026-12-20T19:30:00-05:00')
            ->assertJsonPath('events.0.amount_cents', 2550)
            ->assertJsonPath('events.0.currency', 'USD');
        $this->get('/store')
            ->assertOk()
            ->assertSee('Issue 202 Canonical Event')
            ->assertSee('$25.50')
            ->assertSee('data-countdown-at="2026-12-20T19:30:00-05:00"', false)
            ->assertSee('data-buy="issue-202-event"', false)
            ->assertSee('data-buy-price-value="25.50"', false);
        $this->get(route('store.checkout', ['product' => 'issue-202-event']))
            ->assertOk()
            ->assertSee('Issue 202 Canonical Event')
            ->assertSee('$25.50')
            ->assertSee('Dec 20, 2026 - 7:30 PM')
            ->assertSee('data-product-price-value="25.50"', false);

        $this->post(route('admin.content.archive', $event))->assertRedirect();
        $this->get(route('store.checkout', ['product' => 'issue-202-event']))->assertNotFound();
    }

    public function test_festival_backfill_is_idempotent_and_cms_overrides_the_transition_catalog(): void
    {
        $setting = SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => [
                'slots' => [
                    'event_secondary' => [
                        'title' => 'Festival de la Rosa Dorada',
                        'description' => 'Rock & Folk Pty, Ciudad de Panama 19/ Dic - 7:30 PM',
                        'countdown_at' => '2026-12-19T19:30',
                        'action_type' => 'buy',
                        'product_key' => 'listening',
                    ],
                ],
            ],
            'published_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_08_04_180000_add_cms_album_order_and_store_backfill.php');

        $migration->up();
        $migration->up();

        $festival = EditorialContent::query()->where('purchase_key', 'listening')->sole();
        $this->assertSame(ContentType::Event, $festival->type);
        $this->assertSame('2026-12-16 19:30:00', data_get($festival->metadata, 'starts_at'));
        $this->assertSame(1, $festival->auditLogs()->count());
        $this->assertSame($festival->id, data_get($setting->fresh()->payload, 'slots.event_secondary.content_id'));
        $this->assertSame('2026-12-16 19:30:00', data_get($setting->fresh()->payload, 'slots.event_secondary.countdown_at'));

        $festival->update([
            'metadata' => [
                ...$festival->metadata,
                'price_cents' => 2300,
            ],
        ]);
        Cache::store('array')->flush();

        $product = app(ProductCatalog::class)->find('listening');
        $this->assertSame(2300, $product['amount_cents'] ?? null);
        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('events.0.amount_cents', 2300)
            ->assertJsonPath('events.0.starts_at', '2026-12-16 19:30:00');
        $this->get('/store')->assertOk()->assertSee('$23');
        $this->get('/')
            ->assertOk()
            ->assertSee('Reny Renteria en Concierto')
            ->assertDontSee('Festival de la Rosa Dorada')
            ->assertDontSee('data-buy-price-value="23.00"', false);
        $this->get(route('store.checkout', ['product' => 'listening']))
            ->assertOk()
            ->assertSee('$23')
            ->assertSee('Dec 16, 2026 - 7:30 PM');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $this->post(route('admin.content.archive', $festival))->assertRedirect();
        auth()->logout();
        Cache::store('array')->flush();

        $this->assertNull(app(ProductCatalog::class)->find('listening'));
        $this->get(route('store.checkout', ['product' => 'listening']))->assertNotFound();
        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonMissingPath('storefront.slots.event_secondary');
        $this->get('/store')->assertOk()->assertDontSee('Festival de la Rosa Dorada');
    }

    public function test_linked_store_content_respects_guest_royal_and_preview_audiences(): void
    {
        $secretProduct = EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'Royal CMS Product',
            'visibility' => VisibilityAudience::Royal->value,
            'purchase_key' => 'royal-cms-product',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 3200,
                'currency' => 'USD',
                'inventory' => 10,
                'checkout_enabled' => true,
                'is_active' => true,
                'action_type' => 'buy',
                'cta_label' => 'BUY ROYAL PRODUCT',
            ],
        ]);
        SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => [
                'slots' => [
                    'merch' => [
                        'content_id' => $secretProduct->id,
                        'title' => 'This raw setting must never leak',
                        'action_type' => 'buy',
                        'product_key' => 'royal-cms-product',
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonMissingPath('storefront.slots.merch');
        $this->get('/store')
            ->assertOk()
            ->assertDontSee('Royal CMS Product')
            ->assertDontSee('This raw setting must never leak');

        $royal = User::factory()->royal()->create();
        $this->actingAs($royal)
            ->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('storefront.slots.merch.title', 'Royal CMS Product');
        $this->get('/store')->assertOk()->assertSee('Royal CMS Product');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        $this->get(route('admin.site-editor.preview', ['page' => 'store', 'audience' => 'open']))
            ->assertOk()
            ->assertDontSee('Royal CMS Product')
            ->assertDontSee('This raw setting must never leak');
        $this->get(route('admin.site-editor.preview', ['page' => 'store', 'audience' => 'royal']))
            ->assertOk()
            ->assertSee('Royal CMS Product')
            ->assertSee('Royal audience');
    }

    public function test_published_poll_renders_for_guest_accepts_royal_vote_and_archives(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            'action' => 'publish',
            'type' => ContentType::Poll->value,
            'title' => 'Issue 202 Poll',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'question' => 'Which Issue 202 option?',
                'options' => ['Canonical Store', 'CMS Preview'],
                'eligibility' => VisibilityAudience::Royal->value,
                'results_visibility' => 'public',
            ],
        ])->assertCreated();

        $poll = EditorialContent::query()->sole();
        $pollKey = 'cms-poll-'.$poll->id;

        auth()->logout();
        $this->getJson(route('public-content.payload', 'community'))
            ->assertOk()
            ->assertJsonPath('poll.key', $pollKey)
            ->assertJsonPath('poll.question', 'Which Issue 202 option?');
        $this->get('/community')
            ->assertOk()
            ->assertSee('Which Issue 202 option?')
            ->assertSee('Canonical Store')
            ->assertSee('Sign in to vote');

        $member = User::factory()->create();
        $this->actingAs($member)
            ->postJson(route('community.polls.vote', $pollKey), [
                'option_key' => 'option-1',
                'option_label' => 'Canonical Store',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('poll');

        $royal = User::factory()->royal()->create();
        $this->actingAs($royal)
            ->postJson(route('community.polls.vote', $pollKey), [
                'option_key' => 'forged-option',
                'option_label' => 'Forged option',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('option_key');
        $this->actingAs($royal)
            ->postJson(route('community.polls.vote', $pollKey), [
                'option_key' => 'option-1',
                'option_label' => 'Canonical Store',
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->actingAsAdmin($admin);
        $this->post(route('admin.content.archive', $poll))->assertRedirect();
        auth()->logout();

        $this->getJson(route('public-content.payload', 'community'))
            ->assertOk()
            ->assertJsonPath('poll', null);
        $this->get('/community')->assertOk()->assertDontSee('Which Issue 202 option?');
    }

    /** @return array<string, mixed> */
    private function videoPayload(): array
    {
        return [
            'action' => 'draft',
            'type' => ContentType::Video->value,
            'title' => 'Issue 202 Video',
            'summary' => 'CMS video integration coverage.',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
                'access_tier' => VisibilityAudience::Open->value,
                'meta_title' => 'Issue 202 Video SEO',
                'canonical_url' => 'https://renyrenteria.com/videos/issue-202',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pageSettingsPayload(array $overrides = []): array
    {
        return array_replace([
            'action' => 'publish',
            'eyebrow' => 'CMS Videos',
            'title' => 'Issue 202 Video Hub',
            'subtitle' => 'Everything published in one place',
            'description' => 'Issue 202 page settings coverage.',
            'cover_alt' => 'Reny video page cover',
            'meta_title' => 'Issue 202 Videos SEO',
            'meta_description' => 'Issue 202 meta description.',
            'canonical_url' => 'https://renyrenteria.com/videos',
            'og_title' => 'Issue 202 Videos',
            'og_description' => 'Issue 202 Open Graph description.',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => 'Issue 202 Videos',
            'twitter_description' => 'Issue 202 Twitter description.',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function productPayload(): array
    {
        return [
            'action' => 'publish',
            'type' => ContentType::Product->value,
            'title' => 'Issue 202 Product',
            'summary' => 'One source for Store and checkout.',
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => 'issue-202-product',
            'metadata' => [
                'product_kind' => 'digital',
                'sku' => 'ISSUE-202',
                'price_cents' => 1999,
                'currency' => 'USD',
                'inventory' => 20,
                'checkout_enabled' => true,
                'is_active' => true,
                'action_type' => 'buy',
                'cta_label' => 'BUY ISSUE 202',
                'meta_title' => 'Issue 202 Product SEO',
                'meta_description' => 'Canonical product details.',
                'canonical_url' => 'https://renyrenteria.com/content/issue-202-product',
                'og_title' => 'Issue 202 Product OG',
                'twitter_title' => 'Issue 202 Product Twitter',
            ],
        ];
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
