<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
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
        return [
            'photos' => $this->contentQuery->visibleContents($user, [ContentType::Photo, ContentType::Gallery])
                ->get()
                ->values()
                ->map(fn (EditorialContent $content, int $index): array => $this->photo($content, $index))
                ->all(),
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
}
