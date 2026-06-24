<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->publishedContent(ContentType::DeluxeAlbum, [
            'title' => 'CMS Deluxe Album',
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
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('CMS Deluxe Album')
            ->assertSee('CMS Lead Single')
            ->assertSee('data-buy="royal"', false)
            ->assertSee('data-buy="deluxe"', false)
            ->assertSee('data-free-event-rsvp="concert"', false);

        $html = $response->getContent();

        $this->assertStringNotContainsString('class="tab is-active"', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('class="home-show-card"', $html);
        $this->assertStringContainsString('class="home-royal-pass"', $html);
    }

    public function test_mobile_navigation_uses_shared_compact_sizing_across_public_tabs(): void
    {
        $paths = [
            '/' => 'mobile-bottom-nav home-bottom-nav',
            '/music' => 'mobile-bottom-nav',
            '/videos' => 'mobile-bottom-nav',
            '/photos' => 'mobile-bottom-nav',
            '/community' => 'mobile-bottom-nav',
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

        $css = file_get_contents(resource_path('css/app.css'));

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

    public function test_home_preserves_guest_content_for_authenticated_users(): void
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
            ->assertSee('Authenticated Single')
            ->getContent();

        $memberResponse = $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('class="home-royal-pass"', false)
            ->assertSee('data-buy="royal"', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Watch more')
            ->assertSee('Latest Singles')
            ->assertSee('Authenticated Single')
            ->assertSee('class="single music-item"', false)
            ->assertSee('class="mini-play"', false);

        $memberHtml = $memberResponse->getContent();

        foreach (['home-royal-pass', 'home-video-hero', 'home-shows', 'home-music', 'home-singles'] as $class) {
            $this->assertStringContainsString($class, $guestHtml);
            $this->assertStringContainsString($class, $memberHtml);
        }
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
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('class="home-royal-pass"', false);
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
            ->assertJsonPath('royal_pass.product_key', 'royal');
    }

    public function test_home_renders_latest_singles_with_music_tab_card(): void
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
            ->assertSeeInOrder(['Latest Singles', 'I swear', 'Aguita de coco'])
            ->assertSee('data-music-play', false)
            ->assertSee('class="mini-play"', false)
            ->assertSee('class="single music-item"', false)
            ->assertDontSee('home-single-row', false)
            ->assertDontSee('home-single-play', false);

        preg_match('/<section class="home-singles".*?<\/section>/s', $response->getContent(), $matches);
        $singlesHtml = $matches[0] ?? '';

        $this->assertSame(2, substr_count($singlesHtml, 'class="single music-item"'));
        $this->assertSame(2, substr_count($singlesHtml, 'class="mini-play"'));
        $this->assertStringContainsString('/music/play/', $singlesHtml);
    }

    public function test_authenticated_home_single_playback_is_ready(): void
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
            ->assertSee('Member Play Single')
            ->assertSee('data-play-url="'.route('music.play', $single).'"', false);

        $this->actingAs($user)
            ->getJson(route('music.play', $single))
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('audio_url', 'https://audio.test/member-play-single.mp3');
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
