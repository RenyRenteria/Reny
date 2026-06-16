<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;
use App\Models\UserUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCmsContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_public_pages_consume_visible_cms_content(): void
    {
        $this->publishedContent(ContentType::MusicalAlbum, [
            'title' => 'CMS Deluxe Album',
            'summary' => 'CMS album summary',
            'metadata' => [
                'tracklist' => "Intro\nSingle\nFinale",
            ],
        ]);

        $this->publishedContent(ContentType::Song, [
            'title' => 'CMS Lead Single',
            'metadata' => [
                'artist' => 'Reny CMS',
            ],
        ]);

        $this->publishedContent(ContentType::Video, [
            'title' => 'CMS Video Premiere',
            'summary' => 'CMS video summary',
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
            ],
        ]);

        $this->publishedContent(ContentType::Photo, [
            'title' => 'CMS Photo Drop',
            'metadata' => [
                'caption' => 'CMS photo caption',
            ],
        ]);

        $this->publishedContent(ContentType::Product, [
            'title' => 'CMS Digital Product',
            'purchase_key' => 'cms-digital',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 1200,
            ],
        ]);

        $this->publishedContent(ContentType::Event, [
            'title' => 'CMS Listening Event',
            'purchase_key' => 'cms-listening',
            'metadata' => [
                'event_kind' => 'listening_session',
                'starts_at' => '2026-08-24T20:00',
                'location' => 'Panama City',
                'price_cents' => 4200,
                'ticketing_mode' => 'ticket',
            ],
        ]);

        $this->publishedContent(ContentType::Post, [
            'title' => 'CMS Community Post',
            'body' => 'CMS community body.',
        ]);

        $this->publishedContent(ContentType::Poll, [
            'title' => 'CMS Poll',
            'metadata' => [
                'question' => 'CMS poll question?',
                'options' => ['Studio drop', 'Live drop'],
            ],
        ]);

        $this->get('/')->assertOk()->assertSee('CMS Deluxe Album')->assertSee('CMS Lead Single');
        $this->get('/videos')->assertOk()->assertSee('CMS Video Premiere');
        $this->get('/photos')->assertOk()->assertSee('CMS Photo Drop');
        $this->get('/store')->assertOk()->assertSee('CMS Digital Product')->assertSee('CMS Listening Event');
        $this->get('/community')->assertOk()->assertSee('CMS Community Post')->assertSee('CMS poll question?');
    }

    public function test_public_pages_fall_back_to_last_cached_published_payload_when_cms_query_fails(): void
    {
        $this->publishedContent(ContentType::Photo, [
            'title' => 'Cached CMS Photo',
            'metadata' => [
                'caption' => 'Cached caption',
            ],
        ]);

        $this->get('/photos')
            ->assertOk()
            ->assertSee('Cached CMS Photo');

        Schema::drop('editorial_contents');

        $this->get('/photos')
            ->assertOk()
            ->assertSee('Cached CMS Photo');
    }

    public function test_changing_open_content_to_member_blocks_public_ui_and_backend_access(): void
    {
        $content = $this->publishedContent(ContentType::Post, [
            'title' => 'Gate Switch Post',
            'body' => 'Visible while open.',
            'visibility' => VisibilityAudience::Open->value,
        ]);

        $this->get('/community')->assertOk()->assertSee('Gate Switch Post');
        $this->get(route('public.content.show', $content))->assertOk()->assertSee('Visible while open.');

        $content->update([
            'visibility' => VisibilityAudience::Member->value,
        ]);

        $this->get('/community')->assertOk()->assertDontSee('Gate Switch Post');
        $this->get(route('public.content.show', $content))->assertRedirect(route('login'));

        Schema::drop('editorial_contents');

        $this->get('/community')->assertOk()->assertDontSee('Gate Switch Post');
    }

    public function test_member_and_royal_backend_gates_are_distinct(): void
    {
        $member = User::factory()->create();
        $royal = User::factory()->royal()->create();

        $memberContent = $this->publishedContent(ContentType::Post, [
            'title' => 'Member Account Post',
            'body' => 'Logged-in members can read this.',
            'visibility' => VisibilityAudience::Member->value,
        ]);

        $royalContent = $this->publishedContent(ContentType::Post, [
            'title' => 'Royal Only Post',
            'body' => 'Active Royal users can read this.',
            'visibility' => VisibilityAudience::Royal->value,
        ]);

        $this->get(route('public.content.show', $memberContent))->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get(route('public.content.show', $memberContent))
            ->assertOk()
            ->assertSee('Logged-in members can read this.');

        $this->actingAs($member)
            ->get(route('public.content.show', $royalContent))
            ->assertForbidden();

        $this->actingAs($royal)
            ->get(route('public.content.show', $royalContent))
            ->assertOk()
            ->assertSee('Active Royal users can read this.');
    }

    public function test_purchased_content_survives_expired_royal_access(): void
    {
        $expiredRoyal = User::factory()->expiredRoyal()->create();
        $content = $this->publishedContent(ContentType::Exclusive, [
            'title' => 'Purchased Deluxe Track',
            'body' => 'Unlocked after purchase.',
            'visibility' => VisibilityAudience::Purchased->value,
            'purchase_key' => 'deluxe-drop-001',
        ]);

        $this->actingAs($expiredRoyal)
            ->get(route('public.content.show', $content))
            ->assertForbidden();

        UserUnlock::create([
            'user_id' => $expiredRoyal->id,
            'unlock_type' => 'content',
            'product_key' => 'deluxe-drop-001',
            'title' => $content->title,
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        $this->actingAs($expiredRoyal)
            ->get(route('public.content.show', $content))
            ->assertOk()
            ->assertSee('Unlocked after purchase.');

        $this->actingAs($expiredRoyal)
            ->get('/')
            ->assertOk()
            ->assertSee('Purchased Deluxe Track');
    }

    public function test_server_side_gates_cover_open_member_royal_and_purchased(): void
    {
        $this->publishedVideo('Open CMS Video', VisibilityAudience::Open);
        $this->publishedVideo('Member CMS Video', VisibilityAudience::Member);
        $this->publishedVideo('Royal CMS Video', VisibilityAudience::Royal);
        $purchased = $this->publishedVideo('Purchased CMS Video', VisibilityAudience::Purchased, 'deluxe-drop-001');

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertSee('Open CMS Video')
            ->assertDontSee('Member CMS Video')
            ->assertDontSee('Royal CMS Video')
            ->assertDontSee('Purchased CMS Video');

        $member = User::factory()->create();

        $this->actingAs($member)
            ->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertSee('Open CMS Video')
            ->assertSee('Member CMS Video')
            ->assertDontSee('Royal CMS Video')
            ->assertDontSee('Purchased CMS Video');

        foreach ([User::factory()->royal()->create(), User::factory()->royalGrace()->create()] as $royalUser) {
            $this->actingAs($royalUser)
                ->getJson(route('public-content.payload', 'videos'))
                ->assertOk()
                ->assertSee('Open CMS Video')
                ->assertSee('Member CMS Video')
                ->assertSee('Royal CMS Video')
                ->assertDontSee('Purchased CMS Video');
        }

        foreach ([
            User::factory()->expiredRoyal()->create(),
            User::factory()->cancelledRoyal()->create(),
            User::factory()->onHoldRoyal()->create(),
            User::factory()->refundedRoyal()->create(),
        ] as $nonRoyalUser) {
            $this->assertFalse($nonRoyalUser->hasRoyalAccess());

            $this->actingAs($nonRoyalUser)
                ->getJson(route('public-content.payload', 'videos'))
                ->assertOk()
                ->assertSee('Open CMS Video')
                ->assertSee('Member CMS Video')
                ->assertDontSee('Royal CMS Video')
                ->assertDontSee('Purchased CMS Video');
        }

        $expiredRoyal = User::factory()->expiredRoyal()->create();
        $unlock = UserUnlock::create([
            'user_id' => $expiredRoyal->id,
            'unlock_type' => 'content',
            'product_key' => 'deluxe-drop-001',
            'title' => $purchased->title,
            'source_type' => 'editorial_content',
            'source_id' => (string) $purchased->id,
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        $this->actingAs($expiredRoyal)
            ->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertSee('Purchased CMS Video')
            ->assertDontSee('Royal CMS Video');

        $unlock->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $this->actingAs($expiredRoyal)
            ->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertDontSee('Purchased CMS Video')
            ->assertDontSee('Royal CMS Video');
    }

    public function test_premium_content_and_asset_payloads_are_not_rendered_for_unauthorized_users(): void
    {
        $this->publishedContent(ContentType::Video, [
            'title' => 'Royal Vault Premiere',
            'summary' => 'Premium video payload',
            'visibility' => VisibilityAudience::Royal->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=SECRET_ROYAL_ID',
                'category' => 'music-video',
                'asset_path' => 'private/premium/master-video.mp4',
            ],
        ]);

        $this->get('/videos')
            ->assertOk()
            ->assertDontSee('Royal Vault Premiere')
            ->assertDontSee('SECRET_ROYAL_ID')
            ->assertDontSee('private/premium/master-video.mp4');

        $this->getJson(route('public-content.payload', 'videos'))
            ->assertOk()
            ->assertDontSee('Royal Vault Premiere')
            ->assertDontSee('SECRET_ROYAL_ID')
            ->assertDontSee('private/premium/master-video.mp4');
    }

    public function test_archived_content_stays_out_of_public_lists_without_breaking_direct_references(): void
    {
        $content = EditorialContent::factory()->create([
            'type' => ContentType::Post->value,
            'title' => 'Archived CMS Reference',
            'status' => EditorialStatus::Archived->value,
            'visibility' => VisibilityAudience::Open->value,
            'archived_at' => now(),
        ]);

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Archived CMS Reference');

        $this->get(route('public.content.show', $content))
            ->assertOk()
            ->assertSee('Archived CMS Reference')
            ->assertSee('no longer in public rotation');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedContent(ContentType $type, array $attributes = []): EditorialContent
    {
        return EditorialContent::factory()->published()->create([
            'type' => $type->value,
            'visibility' => VisibilityAudience::Open->value,
            'summary' => null,
            'body' => null,
            'metadata' => [],
            ...$attributes,
        ]);
    }

    private function publishedVideo(
        string $title,
        VisibilityAudience $visibility,
        ?string $purchaseKey = null
    ): EditorialContent {
        return $this->publishedContent(ContentType::Video, [
            'title' => $title,
            'visibility' => $visibility->value,
            'purchase_key' => $purchaseKey,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v='.str($title)->slug(''),
                'category' => 'music-video',
            ],
        ]);
    }
}
