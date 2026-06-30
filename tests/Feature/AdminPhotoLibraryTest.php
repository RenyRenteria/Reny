<?php

namespace Tests\Feature;

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use App\Jobs\ProcessPhotoVariants;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPhotoLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_public_and_member_only_photos_with_server_side_blur(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $response = $this->post(route('cms.photos.upload'), [
            'album_title' => 'Backstage Junio',
            'album_description' => 'Carrete del show',
            'visibility' => [
                0 => PhotoVisibility::Public->value,
                1 => PhotoVisibility::MemberOnly->value,
            ],
            'captions' => [
                0 => 'Public frame',
                1 => 'Royal frame',
            ],
            'files' => [
                UploadedFile::fake()->image('public-frame.jpg', 80, 60)->size(512),
                UploadedFile::fake()->image('royal-frame.jpg', 80, 60)->size(512),
            ],
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('queued', false)
            ->assertJsonPath('photos.0.visibility', PhotoVisibility::Public->value)
            ->assertJsonPath('photos.1.visibility', PhotoVisibility::MemberOnly->value);

        $album = PhotoAlbum::query()->where('title', 'Backstage Junio')->firstOrFail();
        $this->assertSame(2, $album->photos()->count());

        $publicPhoto = Photo::query()->where('caption', 'Public frame')->firstOrFail();
        $memberPhoto = Photo::query()->where('caption', 'Royal frame')->firstOrFail();

        $this->assertSame(PhotoStatus::Active, $publicPhoto->status);
        $this->assertSame(PhotoStatus::Active, $memberPhoto->status);
        $this->assertSame('public', $publicPhoto->public_disk);
        $this->assertSame('local', $memberPhoto->public_disk);

        Storage::disk('local')->assertExists($memberPhoto->original_path);
        Storage::disk('local')->assertExists($memberPhoto->public_path);
        Storage::disk('public')->assertExists($memberPhoto->blurred_path);

        auth()->logout();

        $guestResponse = $this->get('/photos')->assertOk();
        $guestResponse->assertSee('data-photo-locked="true"', false);
        $guestResponse->assertSee(Storage::disk('public')->url($memberPhoto->blurred_path), false);
        $guestResponse->assertDontSee(route('photos.image.show', $memberPhoto), false);

        $royalUser = User::factory()->royal()->create();
        $this->actingAs($royalUser)
            ->get('/photos')
            ->assertOk()
            ->assertSee('data-photo-locked="false"', false)
            ->assertSee(route('photos.image.show', $memberPhoto), false);
    }

    public function test_member_only_optimized_image_route_requires_royal_access(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->post(route('cms.photos.upload'), [
            'visibility' => [0 => PhotoVisibility::MemberOnly->value],
            'files' => [
                UploadedFile::fake()->image('royal-frame.jpg', 80, 60)->size(512),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $photo = Photo::query()
            ->where('visibility', PhotoVisibility::MemberOnly->value)
            ->latest()
            ->firstOrFail();

        auth()->logout();

        $this->get(route('photos.image.show', $photo))->assertRedirect('/login');

        $openUser = User::factory()->create();
        $this->actingAs($openUser)
            ->get(route('photos.image.show', $photo))
            ->assertForbidden();

        $royalUser = User::factory()->royal()->create();
        $this->actingAs($royalUser)
            ->get(route('photos.image.show', $photo))
            ->assertOk();
    }

    public function test_member_only_legacy_photo_retires_public_static_asset(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $legacyRelativePath = 'images/photos/legacy-private.jpg';
        $legacyPublicPath = public_path($legacyRelativePath);

        if (! is_dir(dirname($legacyPublicPath))) {
            mkdir(dirname($legacyPublicPath), 0755, true);
        }

        $legacyImage = UploadedFile::fake()->image('legacy-private.jpg', 80, 60)->size(512);
        copy($legacyImage->getRealPath(), $legacyPublicPath);

        try {
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
            $this->actingAsAdmin($admin);

            $photo = Photo::create([
                'visibility' => PhotoVisibility::Public,
                'status' => PhotoStatus::Active,
                'order_index' => 0,
                'caption' => 'Legacy private frame',
                'metadata' => [
                    'legacy_import' => true,
                    'legacy_asset_path' => $legacyRelativePath,
                    'original_filename' => 'legacy-private.jpg',
                    'title' => 'Legacy Private',
                    'type' => 'Single post',
                    'tone' => 'legacy',
                    'size' => 'standard',
                ],
            ]);

            $this->assertFileExists($legacyPublicPath);

            $this->patch(route('admin.photos.update', $photo), [
                'visibility' => PhotoVisibility::MemberOnly->value,
                'status' => PhotoStatus::Active->value,
                'caption' => $photo->caption,
                'order_index' => $photo->order_index,
            ])->assertRedirect();

            $photo->refresh();

            $this->assertSame(PhotoVisibility::MemberOnly, $photo->visibility);
            $this->assertSame('local', $photo->original_disk);
            $this->assertSame('local', $photo->public_disk);
            $this->assertSame('public', $photo->blurred_disk);
            $this->assertArrayNotHasKey('legacy_asset_path', $photo->metadata);
            $this->assertSame($legacyRelativePath, $photo->metadata['legacy_asset_retired_path']);
            $this->assertFileDoesNotExist($legacyPublicPath);
            Storage::disk('local')->assertExists($photo->original_path);
            Storage::disk('local')->assertExists($photo->public_path);
            Storage::disk('public')->assertExists($photo->blurred_path);

            auth()->logout();

            $this->get('/images/photos/legacy-private.jpg')->assertNotFound();
            $this->get('/photos')
                ->assertOk()
                ->assertSee('data-photo-locked="true"', false)
                ->assertSee(Storage::disk('public')->url($photo->blurred_path), false)
                ->assertDontSee(asset($legacyRelativePath), false);
        } finally {
            if (is_file($legacyPublicPath)) {
                unlink($legacyPublicPath);
            }
        }
    }

    public function test_large_batches_are_queued_in_processing_state(): void
    {
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $files = collect(range(1, 16))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("batch-{$index}.jpg", 20, 20)->size(64))
            ->all();

        $this->post(route('cms.photos.upload'), [
            'album_title' => 'Batch grande',
            'files' => $files,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(ProcessPhotoVariants::class, 16);
        $this->assertSame(16, Photo::query()->where('status', PhotoStatus::Processing->value)->where('metadata->source', 'cms_upload')->count());
    }

    public function test_admin_update_visibility_reorders_and_deletes_physical_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->post(route('cms.photos.upload'), [
            'files' => [
                UploadedFile::fake()->image('one.jpg', 80, 60)->size(512),
                UploadedFile::fake()->image('two.jpg', 80, 60)->size(512),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $photo = Photo::query()->where('metadata->source', 'cms_upload')->oldest('id')->firstOrFail();
        $other = Photo::query()->where('metadata->source', 'cms_upload')->whereKeyNot($photo->id)->firstOrFail();
        $oldPublicPath = $photo->public_path;

        $this->patch(route('admin.photos.update', $photo), [
            'visibility' => PhotoVisibility::MemberOnly->value,
            'status' => PhotoStatus::Active->value,
            'caption' => 'Updated caption',
            'order_index' => 7,
        ])->assertRedirect();

        $photo->refresh();
        $this->assertSame(PhotoVisibility::MemberOnly, $photo->visibility);
        $this->assertSame('Updated caption', $photo->caption);
        $this->assertSame(7, $photo->order_index);
        $this->assertNotSame($oldPublicPath, $photo->public_path);
        Storage::disk('public')->assertMissing($oldPublicPath);

        $this->post(route('admin.photos.reorder'), [
            'order' => [
                $photo->id => 1,
                $other->id => 0,
            ],
        ])->assertRedirect();

        $this->assertSame(1, $photo->fresh()->order_index);
        $this->assertSame(0, $other->fresh()->order_index);

        $paths = [
            [$photo->original_disk, $photo->original_path],
            [$photo->public_disk, $photo->public_path],
            [$photo->blurred_disk, $photo->blurred_path],
            [$photo->thumbnail_disk, $photo->thumbnail_path],
        ];

        $this->delete(route('admin.photos.destroy', $photo))->assertRedirect();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);

        foreach ($paths as [$disk, $path]) {
            Storage::disk($disk)->assertMissing($path);
        }
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
