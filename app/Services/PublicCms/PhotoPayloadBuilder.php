<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\Photo;
use App\Models\User;

class PhotoPayloadBuilder
{
    public function __construct(
        private readonly ContentQuery $contentQuery,
        private readonly PayloadMediaResolver $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        $managedPhotos = Photo::query()
            ->with('album')
            ->active()
            ->ordered()
            ->get()
            ->sort(function (Photo $left, Photo $right): int {
                $leftAlbum = $left->album;
                $rightAlbum = $right->album;
                $leftKey = [
                    $leftAlbum ? 0 : 1,
                    $leftAlbum?->order_index ?? PHP_INT_MAX,
                    $leftAlbum?->id ?? PHP_INT_MAX,
                    $leftAlbum?->cover_photo_id === $left->id ? -1 : $left->order_index,
                    $left->id,
                ];
                $rightKey = [
                    $rightAlbum ? 0 : 1,
                    $rightAlbum?->order_index ?? PHP_INT_MAX,
                    $rightAlbum?->id ?? PHP_INT_MAX,
                    $rightAlbum?->cover_photo_id === $right->id ? -1 : $right->order_index,
                    $right->id,
                ];

                return $leftKey <=> $rightKey;
            })
            ->values();

        $managedPayloads = $managedPhotos
            ->values()
            ->map(fn (Photo $photo, int $index): array => $this->managedPhoto($photo, $index, $user));
        $editorialPayloads = $this->contentQuery->visibleContents($user, [ContentType::Photo, ContentType::Gallery])
            ->get()
            ->values()
            ->map(fn (EditorialContent $content, int $index): array => $this->photo($content, $managedPayloads->count() + $index));

        return [
            'photos' => $managedPayloads->merge($editorialPayloads)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photo(EditorialContent $content, int $index): array
    {
        return [
            'image' => $this->media->metadata($content, 'fallback_image', 'cover.jpg'),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id']) ?? $this->media->metadata($content, 'image_url'),
            'type' => $content->type === ContentType::Gallery ? 'Album' : 'Single post',
            'tone' => $this->media->metadata($content, 'location', 'cms'),
            'title' => $content->title,
            'caption' => $this->media->metadata($content, 'caption', $content->summary ?? ''),
            'size' => ['wide', 'tall', 'standard'][$index % 3],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function managedPhoto(Photo $photo, int $index, ?User $user): array
    {
        $hasAccess = $photo->canExposeOptimizedTo($user);
        $album = $photo->album;
        $title = (string) data_get($photo->metadata, 'title', $photo->titleForDisplay());

        return [
            'id' => $photo->id,
            'album_id' => $photo->album_id,
            'image' => data_get($photo->metadata, 'original_filename', 'cover.jpg'),
            'image_url' => $photo->displayUrl($user),
            'type' => $album ? 'Album' : (string) data_get($photo->metadata, 'type', 'Single post'),
            'tone' => (string) data_get($photo->metadata, 'tone', $album?->title ?: 'cms'),
            'title' => $album?->title ?: $title,
            'caption' => $photo->caption ?: ($album?->description ?? ''),
            'size' => (string) data_get($photo->metadata, 'size', ['wide', 'tall', 'standard'][$index % 3]),
            'visibility' => $photo->visibility->value,
            'locked' => ! $hasAccess,
        ];
    }
}
