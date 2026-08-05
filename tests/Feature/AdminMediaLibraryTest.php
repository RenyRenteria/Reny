<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_public_image_with_required_alt_text(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $response = $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'title' => 'Cover art',
            'alt_text' => 'Reny cover art portrait',
            'is_public' => true,
            'file' => UploadedFile::fake()->image('cover.jpg')->size(512),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('assets.0.is_public', true)
            ->assertJsonPath('assets.0.type', MediaAssetType::Image->value)
            ->assertJsonPath('assets.0.processing_status', MediaProcessingStatus::Ready->value);

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertSame($admin->id, $asset->uploaded_by_id);
        $this->assertSame('Reny cover art portrait', $asset->alt_text);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_public_image_upload_requires_alt_text(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'is_public' => true,
            'file' => UploadedFile::fake()->image('cover.jpg')->size(512),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alt_text');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_admin_can_upload_private_image_without_alt_text_to_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $response = $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'title' => 'Private cover',
            'is_public' => false,
            'file' => UploadedFile::fake()->image('private-cover.jpg')->size(512),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('assets.0.is_public', false)
            ->assertJsonPath('assets.0.url', null);

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertFalse($asset->is_public);
        $this->assertSame('local', $asset->disk);
        $this->assertNull($asset->alt_text);
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_approved_app_server_media_types_can_be_uploaded(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $uploads = [
            [
                'type' => MediaAssetType::Audio->value,
                'file' => UploadedFile::fake()->create('song.mp3', 128, 'audio/mpeg'),
            ],
            [
                'type' => MediaAssetType::Document->value,
                'file' => UploadedFile::fake()->create('credits.pdf', 64, 'application/pdf'),
            ],
            [
                'type' => MediaAssetType::ProductAsset->value,
                'file' => UploadedFile::fake()->create('product.zip', 64, 'application/zip'),
            ],
            [
                'type' => MediaAssetType::Thumbnail->value,
                'alt_text' => 'Video thumbnail alt text',
                'file' => UploadedFile::fake()->image('thumbnail.jpg')->size(256),
            ],
        ];

        foreach ($uploads as $upload) {
            $this->post(route('admin.media.store'), $upload, ['Accept' => 'application/json'])
                ->assertCreated()
                ->assertJsonPath('assets.0.type', $upload['type']);
        }

        $this->assertDatabaseCount('media_assets', count($uploads));
    }

    public function test_replace_preserves_asset_references_and_delete_blocks_until_detached(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'title' => 'Original referenced cover',
            'alt_text' => 'Original cover alt',
            'is_public' => true,
            'file' => UploadedFile::fake()->image('original.jpg')->size(64),
        ], ['Accept' => 'application/json'])->assertCreated();

        $asset = MediaAsset::query()->sole();
        $assetId = $asset->id;
        $oldPath = $asset->path;
        $content = EditorialContent::factory()->create();
        $content->mediaAssets()->attach($asset, [
            'role' => 'cover',
            'sort_order' => 0,
        ]);

        $this->post(route('admin.media.replace', $asset), [
            'title' => 'Replacement referenced cover',
            'alt_text' => 'Replacement cover alt',
            'is_public' => true,
            'file' => UploadedFile::fake()->image('replacement.jpg')->size(64),
        ])->assertRedirect();

        $asset->refresh();
        $this->assertSame($assetId, $asset->id);
        $this->assertSame('Replacement referenced cover', $asset->title);
        $this->assertNotSame($oldPath, $asset->path);
        $this->assertTrue($content->mediaAssets()->whereKey($asset)->exists());
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($asset->path);

        $this->delete(route('admin.media.destroy', $asset))
            ->assertSessionHasErrors('asset');
        $this->assertDatabaseHas('media_assets', ['id' => $assetId]);

        $replacementPath = $asset->path;
        $content->mediaAssets()->detach($asset);
        $content->update(['metadata' => ['gallery_asset_ids' => [$assetId]]]);
        $this->delete(route('admin.media.destroy', $asset))
            ->assertSessionHasErrors('asset');
        $this->assertDatabaseHas('media_assets', ['id' => $assetId]);

        $content->update(['metadata' => []]);
        $this->delete(route('admin.media.destroy', $asset))->assertRedirect();

        $this->assertDatabaseMissing('media_assets', ['id' => $assetId]);
        Storage::disk('public')->assertMissing($replacementPath);
    }

    public function test_app_server_upload_rejects_short_video_and_media_screen_opens_library(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.media.index'))
            ->assertOk()
            ->assertSee('Biblioteca de Fotos y Videos')
            ->assertSee('Recent assets')
            ->assertDontSee('<option value="short_video"', false);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::ShortVideo->value,
            'duration_seconds' => 60,
            'file' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_validates_extension_mime_and_batch_limit(): void
    {
        Storage::fake('public');
        config(['media.batch_limit_bytes' => 10]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Document->value,
            'files' => [
                UploadedFile::fake()->create('notes.pdf', 8, 'application/pdf'),
                UploadedFile::fake()->create('more-notes.pdf', 8, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files');

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'alt_text' => 'Image description',
            'file' => UploadedFile::fake()->create('cover.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_storage_failure_returns_clear_error_without_corrupt_record(): void
    {
        config(['filesystems.disks.public.root' => '/dev/null']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'alt_text' => 'Image description',
            'is_public' => true,
            'file' => UploadedFile::fake()->image('cover.jpg')->size(512),
        ], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Upload failed because app-server storage is unavailable.');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_partial_batch_upload_failure_deletes_stored_files_without_corrupt_record(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $disk = Mockery::mock();

        $disk->shouldReceive('putFileAs')
            ->once()
            ->andReturn('media/image/2026/06/first.jpg');

        $disk->shouldReceive('putFileAs')
            ->once()
            ->andThrow(new RuntimeException('disk quota exceeded'));

        $disk->shouldReceive('delete')
            ->once()
            ->with('media/image/2026/06/first.jpg')
            ->andReturn(true);

        Storage::shouldReceive('disk')
            ->with('public')
            ->times(3)
            ->andReturn($disk);

        try {
            app(MediaLibraryService::class)->storeUploads($admin, [
                'type' => MediaAssetType::Image->value,
                'title' => 'Batch upload',
                'alt_text' => 'Batch image alt text',
                'is_public' => true,
            ], [
                UploadedFile::fake()->image('first.jpg')->size(512),
                UploadedFile::fake()->image('second.jpg')->size(512),
            ]);

            $this->fail('Expected partial upload failure to throw.');
        } catch (MediaUploadException $exception) {
            $this->assertSame('Upload failed because app-server storage is unavailable.', $exception->getMessage());
        }

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_media_asset_can_be_reused_across_editorial_content(): void
    {
        $asset = MediaAsset::create([
            'type' => MediaAssetType::Image->value,
            'title' => 'Reusable cover',
            'disk' => 'public',
            'path' => 'media/image/cover.jpg',
            'original_filename' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'alt_text' => 'Reusable cover alt text',
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);

        $photo = EditorialContent::factory()->create(['type' => ContentType::Photo->value]);
        $product = EditorialContent::factory()->create(['type' => ContentType::Product->value]);

        $photo->mediaAssets()->attach($asset->id, ['role' => 'gallery', 'sort_order' => 1]);
        $product->mediaAssets()->attach($asset->id, ['role' => 'product_asset', 'sort_order' => 1]);

        $this->assertSame(2, $asset->fresh('editorialContents')->editorialContents->count());
    }

    public function test_admin_can_create_mux_direct_upload_without_persisting_secret_values(): void
    {
        config([
            'services.mux.token_id' => 'test-token-id',
            'services.mux.token_secret' => 'test-token-secret',
            'services.mux.cors_origin' => 'https://renyrenteria.com',
        ]);

        Http::fake([
            'https://api.mux.com/video/v1/uploads' => Http::response([
                'data' => [
                    'id' => 'mux-upload-123',
                    'url' => 'https://upload.mux.com/direct-upload',
                    'status' => 'waiting',
                ],
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $response = $this->postJson(route('admin.media.mux.direct-uploads.store'), [
            'title' => 'Behind the scenes',
            'original_filename' => 'behind-the-scenes.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'duration_seconds' => 600,
            'is_public' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('upload_url', 'https://upload.mux.com/direct-upload')
            ->assertJsonPath('asset.mux.upload_id', 'mux-upload-123')
            ->assertJsonPath('asset.processing_status', MediaProcessingStatus::Pending->value);

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertSame('mux', $asset->disk);
        $this->assertTrue($asset->is_public);
        $this->assertSame('mux-upload-123', $asset->mux_upload_id);
        $this->assertStringNotContainsString('test-token-secret', json_encode($asset->toArray()));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.mux.com/video/v1/uploads'
            && $request['new_asset_settings']['passthrough'] === $asset->uuid
            && $request['new_asset_settings']['playback_policies'] === ['public']);
    }

    public function test_admin_can_create_private_mux_direct_upload_with_signed_playback_policy(): void
    {
        config([
            'services.mux.token_id' => 'test-token-id',
            'services.mux.token_secret' => 'test-token-secret',
            'services.mux.cors_origin' => 'https://renyrenteria.com',
        ]);

        Http::fake([
            'https://api.mux.com/video/v1/uploads' => Http::response([
                'data' => [
                    'id' => 'mux-upload-private',
                    'url' => 'https://upload.mux.com/private-direct-upload',
                    'status' => 'waiting',
                ],
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $response = $this->postJson(route('admin.media.mux.direct-uploads.store'), [
            'title' => 'Private behind the scenes',
            'original_filename' => 'private-behind-the-scenes.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'duration_seconds' => 600,
            'is_public' => false,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('asset.is_public', false)
            ->assertJsonPath('asset.mux.upload_id', 'mux-upload-private');

        $asset = MediaAsset::query()->firstOrFail();

        $this->assertFalse($asset->is_public);
        $this->assertSame('mux', $asset->disk);
        $this->assertSame('mux-upload-private', $asset->mux_upload_id);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.mux.com/video/v1/uploads'
            && $request['new_asset_settings']['passthrough'] === $asset->uuid
            && $request['new_asset_settings']['playback_policies'] === ['signed']);
    }

    public function test_mux_direct_upload_failure_leaves_no_media_record(): void
    {
        config([
            'services.mux.token_id' => 'test-token-id',
            'services.mux.token_secret' => 'test-token-secret',
        ]);

        Http::fake([
            'https://api.mux.com/video/v1/uploads' => Http::response(['error' => 'bad gateway'], 502),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.media.mux.direct-uploads.store'), [
            'original_filename' => 'behind-the-scenes.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'duration_seconds' => 600,
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Mux could not create a direct upload URL.');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_mux_direct_upload_validates_extension_mime_size_and_duration(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.media.mux.direct-uploads.store'), [
            'original_filename' => 'behind-the-scenes.avi',
            'mime_type' => 'video/x-msvideo',
            'size_bytes' => (5 * 1024 * 1024 * 1024) + 1,
            'duration_seconds' => (20 * 60) + 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_filename', 'mime_type', 'size_bytes', 'duration_seconds']);

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_mux_webhook_signature_updates_asset_processing_state(): void
    {
        config(['services.mux.webhook_secret' => 'test-webhook-secret']);

        $asset = MediaAsset::create([
            'type' => MediaAssetType::ShortVideo->value,
            'title' => 'Waiting video',
            'disk' => 'mux',
            'path' => 'mux://uploads/mux-upload-123',
            'original_filename' => 'waiting-video.mp4',
            'extension' => 'mp4',
            'size_bytes' => 1024,
            'processing_status' => MediaProcessingStatus::Processing->value,
            'mux_upload_id' => 'mux-upload-123',
            'mux_asset_id' => 'mux-asset-123',
        ]);

        $payload = [
            'id' => 'event-123',
            'type' => 'video.asset.ready',
            'data' => [
                'id' => 'mux-asset-123',
                'status' => 'ready',
                'passthrough' => $asset->uuid,
                'playback_ids' => [
                    ['id' => 'playback-123', 'policy' => 'public'],
                ],
            ],
        ];

        $this->postSignedMuxWebhook($payload)
            ->assertOk()
            ->assertJsonPath('media_asset_id', $asset->id);

        $asset = $asset->fresh();

        $this->assertSame(MediaProcessingStatus::Ready, $asset->processing_status);
        $this->assertSame('playback-123', $asset->mux_playback_id);
        $this->assertSame('video.asset.ready', $asset->metadata['mux_last_event_type']);
    }

    public function test_mux_webhook_error_and_cancelled_events_mark_asset_failed(): void
    {
        config(['services.mux.webhook_secret' => 'test-webhook-secret']);

        $cases = [
            'video.asset.errored' => [
                'asset' => [
                    'mux_upload_id' => 'mux-upload-asset-error',
                    'mux_asset_id' => 'mux-asset-error',
                ],
                'data' => [
                    'id' => 'mux-asset-error',
                    'status' => 'errored',
                    'errors' => [
                        'messages' => ['Transcode failed.'],
                    ],
                ],
                'mux_status' => 'errored',
                'mux_error' => 'Transcode failed.',
            ],
            'video.upload.errored' => [
                'asset' => [
                    'mux_upload_id' => 'mux-upload-error',
                ],
                'data' => [
                    'id' => 'mux-upload-error',
                    'status' => 'errored',
                    'error' => [
                        'message' => 'Upload failed before processing.',
                    ],
                ],
                'mux_status' => 'errored',
                'mux_error' => 'Upload failed before processing.',
            ],
            'video.upload.cancelled' => [
                'asset' => [
                    'mux_upload_id' => 'mux-upload-cancelled',
                ],
                'data' => [
                    'id' => 'mux-upload-cancelled',
                    'status' => 'cancelled',
                    'error' => 'Upload cancelled by uploader.',
                ],
                'mux_status' => 'cancelled',
                'mux_error' => 'Upload cancelled by uploader.',
            ],
        ];

        foreach ($cases as $eventType => $case) {
            $asset = $this->createMuxAsset($case['asset']);
            $payload = [
                'id' => 'event-'.str_replace('.', '-', $eventType),
                'type' => $eventType,
                'data' => $case['data'],
            ];

            $this->postSignedMuxWebhook($payload)
                ->assertOk()
                ->assertJsonPath('media_asset_id', $asset->id);

            $asset = $asset->fresh();

            $this->assertSame(MediaProcessingStatus::Failed, $asset->processing_status);
            $this->assertSame($case['mux_status'], $asset->mux_status);
            $this->assertSame($case['mux_error'], $asset->mux_error);
            $this->assertSame($eventType, $asset->metadata['mux_last_event_type']);
        }
    }

    public function test_mux_webhook_rejects_invalid_signature(): void
    {
        config(['services.mux.webhook_secret' => 'test-webhook-secret']);

        $asset = MediaAsset::create([
            'type' => MediaAssetType::ShortVideo->value,
            'title' => 'Waiting video',
            'disk' => 'mux',
            'path' => 'mux://uploads/mux-upload-123',
            'original_filename' => 'waiting-video.mp4',
            'extension' => 'mp4',
            'size_bytes' => 1024,
            'processing_status' => MediaProcessingStatus::Processing->value,
            'mux_upload_id' => 'mux-upload-123',
            'mux_asset_id' => 'mux-asset-123',
        ]);

        $body = json_encode([
            'type' => 'video.asset.ready',
            'data' => ['id' => 'mux-asset-123', 'status' => 'ready'],
        ]);

        $this->call('POST', route('mux.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MUX_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
        ], $body)->assertUnauthorized();

        $this->assertSame(MediaProcessingStatus::Processing, $asset->fresh()->processing_status);
    }

    private function createMuxAsset(array $overrides = []): MediaAsset
    {
        $uploadId = $overrides['mux_upload_id'] ?? 'mux-upload-123';

        return MediaAsset::create(array_merge([
            'type' => MediaAssetType::ShortVideo->value,
            'title' => 'Waiting video',
            'disk' => 'mux',
            'path' => 'mux://uploads/'.$uploadId,
            'original_filename' => 'waiting-video.mp4',
            'extension' => 'mp4',
            'size_bytes' => 1024,
            'processing_status' => MediaProcessingStatus::Processing->value,
        ], $overrides));
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    private function postSignedMuxWebhook(array $payload)
    {
        $body = json_encode($payload);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'test-webhook-secret');

        return $this->call('POST', route('mux.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MUX_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body);
    }
}
