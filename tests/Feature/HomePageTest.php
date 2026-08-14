<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-13 11:34:00', 'America/Panama'));
        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_home_page_composes_cms_video_store_and_music_sources(): void
    {
        $this->publishedContent(ContentType::Video, [
            'title' => 'CMS Home Video',
            'summary' => 'Featured from Videos CMS',
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
            ],
        ]);

        $this->publishedContent(ContentType::MusicalAlbum, [
            'title' => 'CMS Album',
            'metadata' => [
                'track_count' => '27',
            ],
        ]);

        $this->publishedContent(ContentType::Song, [
            'title' => 'CMS Lead Single',
            'metadata' => [
                'release_date_member_view' => '2026-07-01T10:00',
                'release_date_open_view' => '2026-07-02T10:00',
            ],
        ]);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('data-analytics-screen="home"', false)
            ->assertSee('CMS Home Video')
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&amp;mute=1&amp;playsinline=1&amp;rel=0', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertDontSee('Festival de la Rosa Dorada')
            ->assertSee('CMS Album')
            ->assertDontSee('CMS Lead Single')
            ->assertDontSee('Latest Singles')
            ->assertSee('data-buy="royal"', false)
            ->assertSee('data-royal-pass-option="royal"', false)
            ->assertSee('data-buy-image="'.asset('images/store/royal-pass.png').'"', false)
            ->assertSee('Unlock Royal Pass')
            ->assertSee('data-royal-pass-selected="true"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-disabled="false"', false)
            ->assertDontSee('data-buy="deluxe"', false)
            ->assertDontSee('Buy Deluxe')
            ->assertDontSee(route('store.checkout', ['product' => 'deluxe']), false)
            ->assertSee('data-free-event-rsvp="concert"', false);

        $html = $response->getContent();

        $this->assertStringNotContainsString('class="tab is-active"', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('class="home-show-card"', $html);
        $this->assertStringContainsString('class="home-royal-pass is-selected"', $html);
        $this->assertStringContainsString('class="home-royal-pass-selector"', $html);
        $this->assertStringNotContainsString('data-requires-plan-selection="true"', $html);
        $this->assertStringNotContainsString('role="button"', $html);
    }

    public function test_home_page_uses_take_a_bite_as_the_default_video_premiere(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/UWDLtZCoTag?autoplay=1&amp;mute=1&amp;playsinline=1&amp;rel=0', false)
            ->assertSee('https://www.youtube.com/watch?v=UWDLtZCoTag', false)
            ->assertSee('Reny Renteria - Take a bite (Official Music Video)')
            ->assertSee('class="stage-lights"', false)
            ->assertSee(asset('images/reny-renteria-logo-white.png'), false);
    }

    public function test_home_renders_only_the_next_event_with_a_realtime_countdown_between_details_and_cta(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->assertSee('data-countdown-at="2026-09-21T19:30:00-05:00"', false)
            ->assertDontSee('data-countdown-at="2026-12-16T19:30:00-05:00"', false)
            ->getContent();

        preg_match_all('/<article class="home-show-card">(.*?)<\/article>/s', $html, $matches);

        $this->assertCount(1, $matches[1]);

        foreach ($matches[1] as $eventCard) {
            $detailsPosition = strpos($eventCard, 'class="home-show-copy"');
            $countdownPosition = strpos($eventCard, 'class="home-event-countdown"');
            $actionsPosition = strpos($eventCard, 'class="home-show-actions"');

            $this->assertNotFalse($detailsPosition);
            $this->assertNotFalse($countdownPosition);
            $this->assertNotFalse($actionsPosition);
            $this->assertLessThan($countdownPosition, $detailsPosition);
            $this->assertLessThan($actionsPosition, $countdownPosition);
            $this->assertSame(4, substr_count($eventCard, 'data-countdown-unit='));
        }

        $css = $this->frontendCssSource();
        $javascript = $this->frontendJavaScriptSource();

        $this->assertMatchesRegularExpression(
            '/@media \(hover: hover\) and \(pointer: fine\)\s*\{\s*\.home-shell \.home-show-card:hover/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression('/\.home-show-card:(?:active|focus-within)/', $css);
        $this->assertStringContainsString("node.dataset.countdownDisplay === 'segments'", $javascript);
        $this->assertStringContainsString('? 1000', $javascript);
    }

    public function test_mobile_show_card_restores_its_contrast_after_hover(): void
    {
        $css = $this->frontendCssSource();

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 53\.75rem\).*\.home-shell \.home-show-card:hover\s*\{\s*border-color:\s*rgba\(255, 228, 153, 0\.36\);\s*background:\s*radial-gradient\(circle at 100% 0, rgba\(231, 170, 81, 0\.15\), transparent 38%\),\s*linear-gradient\(135deg, rgba\(23, 17, 11, 0\.98\), rgba\(43, 31, 20, 0\.96\)\);/s',
            $css,
        );
    }

    public function test_home_selects_the_nearest_future_event_by_instant_across_timezones(): void
    {
        $this->view('home', [
            'publicCms' => [
                'storefront' => [
                    'slots' => [
                        'event_primary' => [
                            'title' => 'Later Panama Show',
                            'countdown_at' => '2026-09-21 19:30:00',
                            'timezone' => 'America/Panama',
                        ],
                        'event_secondary' => [
                            'title' => 'Sooner New York Show',
                            'countdown_at' => '2026-09-21 19:30:00',
                            'timezone' => 'America/New_York',
                        ],
                    ],
                ],
            ],
            'rsvpTickets' => [],
        ])
            ->assertSee('Sooner New York Show')
            ->assertSee('data-countdown-at="2026-09-21T19:30:00-04:00"', false)
            ->assertDontSee('Later Panama Show')
            ->assertDontSee('data-countdown-at="2026-09-21T19:30:00-05:00"', false);
    }

    public function test_home_uses_future_starts_at_when_countdown_at_is_blank(): void
    {
        $response = $this->view('home', [
            'publicCms' => [
                'storefront' => [
                    'slots' => [
                        'event_primary' => [
                            'title' => 'Future Starts At Show',
                            'countdown_at' => '',
                            'starts_at' => '2026-08-14 19:30:00',
                            'timezone' => 'America/Panama',
                        ],
                    ],
                ],
            ],
            'rsvpTickets' => [],
        ]);

        $response
            ->assertSee('Future Starts At Show')
            ->assertSee('data-countdown-at="2026-08-14T19:30:00-05:00"', false);

        $this->assertSame(1, substr_count((string) $response, 'class="home-show-card"'));
    }

    public function test_home_ignores_expired_and_invalid_events_when_selecting_the_next_show(): void
    {
        $response = $this->view('home', [
            'publicCms' => [
                'storefront' => [],
                'events' => [
                    [
                        'title' => 'Expired Show',
                        'starts_at' => '2026-08-13 10:00:00',
                        'timezone' => 'America/Panama',
                    ],
                    [
                        'title' => 'Invalid Date Show',
                        'starts_at' => 'not-a-date',
                        'timezone' => 'America/Panama',
                    ],
                    [
                        'title' => 'Next Valid Show',
                        'starts_at' => '2026-08-14 20:00:00',
                        'timezone' => 'America/Panama',
                    ],
                ],
            ],
            'rsvpTickets' => [],
        ]);

        $response
            ->assertSee('Next Valid Show')
            ->assertDontSee('Expired Show')
            ->assertDontSee('Invalid Date Show');

        $this->assertSame(1, substr_count((string) $response, 'class="home-show-card"'));
    }

    public function test_home_renders_no_card_when_all_events_are_expired_or_start_exactly_now(): void
    {
        $response = $this->view('home', [
            'publicCms' => [
                'storefront' => [],
                'events' => [
                    [
                        'title' => 'Expired Show',
                        'starts_at' => '2026-08-13 10:00:00',
                        'timezone' => 'America/Panama',
                    ],
                    [
                        'title' => 'Starting Now Show',
                        'starts_at' => '2026-08-13 11:34:00',
                        'timezone' => 'America/Panama',
                    ],
                ],
            ],
            'rsvpTickets' => [],
        ]);

        $response
            ->assertDontSee('Expired Show')
            ->assertDontSee('Starting Now Show');

        $this->assertSame(0, substr_count((string) $response, 'class="home-show-card"'));
    }

    public function test_home_countdown_uses_the_canonical_event_timezone(): void
    {
        $event = $this->publishedContent(ContentType::Event, [
            'title' => 'New York Evening Show',
            'purchase_key' => 'new-york-evening-show',
            'metadata' => [
                'starts_at' => '2026-09-21 19:30:00',
                'timezone' => 'America/New_York',
                'location' => 'New York, NY',
                'ticketing_mode' => 'rsvp',
                'action_type' => 'rsvp',
                'cta_label' => 'RSVP',
            ],
        ]);
        $storefront = app(StorefrontSettingsService::class)->defaults();
        data_set($storefront, 'slots.event_primary.content_id', $event->id);

        SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => $storefront,
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('New York Evening Show')
            ->assertSee('data-countdown-at="2026-09-21T19:30:00-04:00"', false)
            ->assertDontSee('data-countdown-at="2026-09-21T19:30:00-05:00"', false);

        $this->getJson(route('public-content.payload', 'home'))
            ->assertOk()
            ->assertJsonPath('storefront.slots.event_primary.timezone', 'America/New_York');
    }

    public function test_mobile_navigation_uses_shared_compact_sizing_across_public_tabs(): void
    {
        $paths = [
            '/' => 'mobile-bottom-nav home-bottom-nav',
            '/music' => 'mobile-bottom-nav',
            '/videos' => 'mobile-bottom-nav',
            '/photos' => 'mobile-bottom-nav',
            '/royals' => 'mobile-bottom-nav',
            '/shows' => 'mobile-bottom-nav',
            '/store' => 'mobile-bottom-nav',
        ];

        foreach ($paths as $path => $classes) {
            $html = $this->get($path)
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/<nav class="'.preg_quote($classes, '/').'" aria-label="Mobile menu">.*?<\/nav>/s',
                $html,
                "Missing mobile nav on [{$path}]"
            );

            preg_match('/<nav class="'.preg_quote($classes, '/').'" aria-label="Mobile menu">(.*?)<\/nav>/s', $html, $matches);
            $navHtml = $matches[1] ?? '';

            $this->assertSame(5, substr_count($navHtml, '<a '), "Unexpected mobile nav item count on [{$path}]");

            if ($path === '/') {
                $this->assertStringNotContainsString('is-active', $navHtml);
                $this->assertStringNotContainsString('aria-current="page"', $navHtml);
            }
        }

        $css = $this->frontendCssSource();

        $this->assertDoesNotMatchRegularExpression('/\.home-bottom-nav\s*\{[^}]*display\s*:\s*none\s*;/s', $css);
        $this->assertDoesNotMatchRegularExpression('/\.home-bottom-nav\s*\{[^}]*height\s*:/s', $css);
        $this->assertDoesNotMatchRegularExpression('/\.home-bottom-nav svg\s*\{/s', $css);
        $this->assertMatchesRegularExpression(
            '/\.mobile-bottom-nav\s*\{[^}]*height\s*:\s*calc\(3\.1875rem \+ env\(safe-area-inset-bottom\)\);/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mobile-bottom-nav svg\s*\{[^}]*width\s*:\s*1\.75rem;[^}]*height\s*:\s*1\.75rem;/s',
            $css
        );
    }

    public function test_home_preserves_guest_content_for_authenticated_users_without_singles(): void
    {
        $this->publishedContent(ContentType::Song, [
            'title' => 'Authenticated Single',
            'metadata' => [
                'audio_url' => 'https://audio.test/authenticated-single.mp3',
            ],
        ]);

        $user = User::factory()->create();
        $guestHtml = $this->get('/')
            ->assertOk()
            ->assertDontSee('Authenticated Single')
            ->assertDontSee('Latest Singles')
            ->getContent();

        $memberResponse = $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('class="home-royal-pass is-selected"', false)
            ->assertSee('data-buy="royal"', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertDontSee('Festival de la Rosa Dorada')
            ->assertSee('Watch more')
            ->assertDontSee('Latest Singles')
            ->assertDontSee('Authenticated Single')
            ->assertDontSee('class="single music-item"', false)
            ->assertDontSee('class="mini-play"', false);

        $memberHtml = $memberResponse->getContent();

        foreach (['home-royal-pass', 'home-video-hero', 'home-shows', 'home-music'] as $class) {
            $this->assertStringContainsString($class, $guestHtml);
            $this->assertStringContainsString($class, $memberHtml);
        }

        $this->assertStringNotContainsString('home-singles', $guestHtml);
        $this->assertStringNotContainsString('home-singles', $memberHtml);
    }

    public function test_home_falls_back_to_storefront_events_when_payload_events_are_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->view('home', [
            'publicCms' => [
                'events' => [],
            ],
            'rsvpTickets' => [],
        ]);

        $response
            ->assertSee('Upcoming Shows')
            ->assertSee('Reny Renteria en Concierto')
            ->assertDontSee('Festival de la Rosa Dorada')
            ->assertSee('class="home-royal-pass is-selected"', false);
    }

    public function test_home_prefers_current_storefront_events_over_legacy_cached_events(): void
    {
        SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => [
                'slots' => [
                    'event_primary' => [
                        'title' => 'Current Published Event',
                        'description' => "Current venue\nNov 15 - 8:00 PM",
                        'cta_label' => 'CURRENT CTA',
                        'product_key' => 'current-published-event',
                        'countdown_at' => '2026-11-15 20:00:00',
                        'timezone' => 'America/Panama',
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        $this->view('home', [
            'publicCms' => [
                'events' => [
                    [
                        'title' => 'Stale Cached Event',
                        'description' => "Old venue\nOct 1 - 7:00 PM",
                        'cta_label' => 'OLD CTA',
                        'product_key' => 'stale-cached-event',
                    ],
                ],
            ],
            'rsvpTickets' => [],
        ])
            ->assertSee('Current Published Event')
            ->assertSee('CURRENT CTA')
            ->assertSee('data-free-event-rsvp="current-published-event"', false)
            ->assertDontSee('Stale Cached Event')
            ->assertDontSee('OLD CTA');
    }

    public function test_home_payload_is_available_from_public_cms_endpoint(): void
    {
        $this->publishedContent(ContentType::Song, [
            'title' => 'Payload Single',
        ]);

        $this->getJson(route('public-content.payload', 'home'))
            ->assertOk()
            ->assertJsonPath('_cms_source', 'cms')
            ->assertJsonPath('singles.0.title', 'Payload Single')
            ->assertJsonPath('events.0.title', 'Reny Renteria en Concierto')
            ->assertJsonPath('storefront.slots.event_primary.title', 'Reny Renteria en Concierto')
            ->assertJsonPath('storefront.slots.album.title', 'Work in Progress')
            ->assertJsonPath('storefront.slots.album.action_type', 'link')
            ->assertJsonPath('royal_pass.product_key', 'royal');
    }

    public function test_home_does_not_render_singles_section(): void
    {
        $this->publishedContent(ContentType::Song, [
            'title' => 'I swear',
            'published_at' => now()->subMinute(),
            'metadata' => [
                'audio_url' => 'https://audio.test/i-swear.mp3',
            ],
        ]);
        $this->publishedContent(ContentType::Song, [
            'title' => 'Aguita de coco',
            'published_at' => now()->subMinutes(2),
            'metadata' => [
                'audio_url' => 'https://audio.test/aguita-de-coco.mp3',
            ],
        ]);

        $response = $this->get('/')
            ->assertOk()
            ->assertDontSee('Latest Singles')
            ->assertDontSee('I swear')
            ->assertDontSee('Aguita de coco')
            ->assertDontSee('home-singles', false)
            ->assertDontSee('home-single-list', false)
            ->assertSee('Latest Album');
    }

    public function test_authenticated_single_playback_remains_available_outside_homepage(): void
    {
        $single = $this->publishedContent(ContentType::Song, [
            'title' => 'Member Play Single',
            'metadata' => [
                'audio_url' => 'https://audio.test/member-play-single.mp3',
            ],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('Member Play Single')
            ->assertDontSee('data-play-url="'.route('music.play', $single).'"', false);

        $this->actingAs($user)
            ->getJson(route('music.play', $single))
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('audio_url', 'https://audio.test/member-play-single.mp3');
    }

    public function test_authenticated_home_hides_singles_when_home_payload_falls_back(): void
    {
        $this->publishedContent(ContentType::Song, [
            'title' => 'Fallback Visible Single',
            'metadata' => [
                'audio_url' => 'https://audio.test/fallback-visible-single.mp3',
            ],
        ]);

        Schema::drop('site_page_settings');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Latest Singles')
            ->assertDontSee('Fallback Visible Single')
            ->assertDontSee('class="single music-item"', false)
            ->assertDontSee('class="mini-play"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedContent(ContentType $type, array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()->create([
            'type' => $type,
            'status' => EditorialStatus::Published,
            'published_at' => now(),
            ...$overrides,
        ]);
    }
}
