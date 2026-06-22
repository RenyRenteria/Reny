<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditorialFormsPreviewSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_editorial_screen_stays_on_enter_screen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.editorial.index'))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Contenido de tu Sitio')
            ->assertDontSee('Crear contenido');
    }

    public function test_admin_content_index_stays_on_enter_screen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.content.index', ['section' => 'music']))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Musica')
            ->assertDontSee('Music queue item');
    }

    public function test_editor_can_prepare_one_piece_of_each_content_type(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $asset = $this->readyAsset();

        $this->actingAsAdmin($editor);

        foreach (ContentType::cases() as $type) {
            $this->postJson(route('admin.editorial.drafts.store'), $this->payloadFor($type, $asset))
                ->assertOk()
                ->assertJsonPath('type', $type->value)
                ->assertJsonPath('status', EditorialStatus::Draft->value)
                ->assertJsonPath('needs_approval', true);
        }

        $this->assertSame(count(ContentType::cases()), EditorialContent::query()->count());
    }

    public function test_admin_can_publish_one_piece_of_each_content_type(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $asset = $this->readyAsset();

        $this->actingAsAdmin($admin);

        foreach (ContentType::cases() as $type) {
            $this->postJson(route('admin.editorial.publish'), $this->payloadFor($type, $asset))
                ->assertOk()
                ->assertJsonPath('type', $type->value)
                ->assertJsonPath('status', EditorialStatus::Published->value)
                ->assertJsonPath('needs_approval', false);
        }

        $this->assertSame(
            count(ContentType::cases()),
            EditorialContent::query()->where('status', EditorialStatus::Published->value)->count()
        );
    }

    public function test_private_preview_requires_admin_session_and_stays_on_enter_screen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $content = EditorialContent::factory()->create([
            'title' => 'Private preview draft',
            'body' => 'Preview body',
        ]);

        $this->get(route('admin.editorial.preview', $content))
            ->assertRedirect(route('admin.login'));

        $this->actingAsAdmin($admin);

        $this->get(route('admin.editorial.preview', $content))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Private preview draft');
    }

    public function test_scheduling_uses_panama_timezone(): void
    {
        $previousPhpTimezone = date_default_timezone_get();
        $previousAppTimezone = config('app.timezone');

        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        try {
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
            $asset = $this->readyAsset();

            $this->actingAsAdmin($admin);

            $this->postJson(route('admin.editorial.schedule'), [
                ...$this->payloadFor(ContentType::Post, $asset),
                'scheduled_at' => '2026-07-01T09:30',
                'release_windows' => [
                    [
                        'audience' => VisibilityAudience::Member->value,
                        'starts_at' => '2026-07-01T09:30',
                    ],
                    [
                        'audience' => VisibilityAudience::Open->value,
                        'starts_at' => '2026-07-08T09:30',
                    ],
                ],
            ])
                ->assertOk()
                ->assertJsonPath('status', EditorialStatus::Scheduled->value);

            $content = EditorialContent::query()->firstOrFail();

            $this->assertSame(
                '2026-07-01 09:30:00',
                $content->scheduled_at->timezone('America/Panama')->format('Y-m-d H:i:s')
            );
            $this->assertSame(User::ROLE_ADMIN, $content->scheduledBy->role);
            $this->assertCount(2, $content->releaseWindows);
            $this->assertSame(
                '2026-07-01 09:30:00',
                $content->releaseWindows
                    ->first(fn ($window): bool => $window->audience === VisibilityAudience::Member)
                    ->starts_at
                    ->timezone('America/Panama')
                    ->format('Y-m-d H:i:s')
            );

            $this->assertSame(EditorialStatus::Scheduled, $content->status);
        } finally {
            date_default_timezone_set($previousPhpTimezone);
            config(['app.timezone' => $previousAppTimezone]);
        }
    }

    public function test_per_type_validation_blocks_incomplete_payloads(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAsAdmin($editor);

        $this->postJson(route('admin.editorial.drafts.store'), [
            'type' => ContentType::Song->value,
            'title' => 'Incomplete song',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.release_date_member_view', 'metadata.release_date_open_view', 'media_asset_ids']);

        $this->postJson(route('admin.editorial.drafts.store'), [
            'type' => ContentType::Poll->value,
            'title' => 'Incomplete poll',
            'metadata' => [
                'question' => 'Pick one',
                'options' => ['Only one', ''],
                'eligibility' => 'royal',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.options']);
    }

    private function readyAsset(): MediaAsset
    {
        return MediaAsset::create([
            'type' => MediaAssetType::Image->value,
            'title' => 'Reusable campaign asset',
            'disk' => 'public',
            'path' => 'media/reusable.jpg',
            'original_filename' => 'reusable.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'is_public' => true,
            'alt_text' => 'Reusable campaign asset',
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);
    }

    private function payloadFor(ContentType $type, MediaAsset $asset): array
    {
        $base = [
            'type' => $type->value,
            'title' => 'CMS '.str_replace('_', ' ', $type->value),
            'summary' => 'Prepared through the admin CMS form.',
            'visibility' => VisibilityAudience::Royal->value,
            'media_asset_ids' => [$asset->id],
        ];

        return match ($type) {
            ContentType::Song => [
                ...$base,
                'metadata' => [
                    'release_date_member_view' => '2026-07-01T10:00',
                    'release_date_open_view' => '2026-07-02T10:00',
                ],
            ],
            ContentType::MusicalAlbum => [
                ...$base,
                'metadata' => [
                    'release_date_member_view' => '2026-07-01T10:00',
                    'release_date_open_view' => '2026-07-02T10:00',
                    'tracks' => [
                        ['track_name' => 'Intro'],
                    ],
                ],
            ],
            ContentType::DeluxeAlbum => [
                ...$base,
                'purchase_key' => 'deluxe-001',
                'metadata' => [
                    'package_title' => 'Deluxe room',
                    'package_notes' => 'Exclusive extras',
                ],
            ],
            ContentType::MusicPlaylist => [
                ...$base,
                'metadata' => [
                    'tracks' => ['song:1'],
                ],
            ],
            ContentType::Video => [
                ...$base,
                'metadata' => [
                    'video_url' => 'https://www.youtube.com/watch?v=abc12345678',
                    'category' => 'behind the scenes',
                    'playlist' => 'studio',
                ],
            ],
            ContentType::Photo => [
                ...$base,
                'metadata' => [
                    'caption' => 'Studio still',
                    'location' => 'Panama City',
                ],
            ],
            ContentType::Gallery => [
                ...$base,
                'metadata' => [
                    'gallery_theme' => 'Backstage',
                    'caption' => 'Gallery caption',
                ],
            ],
            ContentType::Post => [
                ...$base,
                'body' => 'Long-form editorial body.',
                'metadata' => [
                    'link_url' => 'https://renyrenteria.com',
                ],
            ],
            ContentType::Poll => [
                ...$base,
                'metadata' => [
                    'question' => 'Which version should drop first?',
                    'options' => ['Acoustic', 'Live'],
                    'eligibility' => 'royal',
                    'results_visibility' => 'private',
                ],
            ],
            ContentType::Product => [
                ...$base,
                'purchase_key' => 'product-001',
                'metadata' => [
                    'product_type' => 'digital',
                    'price' => 12,
                    'inventory' => 500,
                    'sku' => 'DIG-001',
                ],
            ],
            ContentType::Event => [
                ...$base,
                'metadata' => [
                    'event_type' => 'listening session',
                    'event_starts_at' => '2026-08-01T20:00',
                    'venue' => 'Panama City',
                    'inventory' => 120,
                    'price' => 25,
                ],
            ],
            ContentType::Drop => [
                ...$base,
                'metadata' => [
                    'drop_window' => '2026-08-15T09:00',
                    'inventory' => 250,
                    'bundle_notes' => 'Limited Royal bundle',
                ],
            ],
            ContentType::Exclusive => [
                ...$base,
                'visibility' => VisibilityAudience::Royal->value,
                'metadata' => [
                    'access_note' => 'Royal-only room',
                    'unlocked_by' => 'Royal Pass',
                ],
            ],
        };
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
