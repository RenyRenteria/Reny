<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Support\Arr;

class CommunityPayloadBuilder
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
        $contents = $this->contentQuery->visibleContents($user, [ContentType::Post, ContentType::Poll])->get();
        $poll = $contents->firstWhere('type', ContentType::Poll);

        return [
            'posts' => $contents
                ->where('type', ContentType::Post)
                ->values()
                ->map(fn (EditorialContent $content): array => $this->post($content))
                ->all(),
            'poll' => $poll instanceof EditorialContent ? $this->poll($poll) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(EditorialContent $content): array
    {
        return [
            'key' => 'cms-post-'.$content->id,
            'title' => $content->title,
            'time' => $content->published_at?->diffForHumans() ?? 'Published',
            'body' => $content->body ?: $content->summary ?: '',
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id']),
            'cta' => 'View Reny note',
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function poll(EditorialContent $content): array
    {
        $options = collect(Arr::wrap($this->media->metadata($content, 'options')))
            ->filter()
            ->values()
            ->map(fn (string $option, int $index): array => [
                'key' => 'option-'.($index + 1),
                'label' => $option,
                'percent' => [42, 34, 24, 18, 12, 8, 6, 4][$index] ?? 10,
            ])
            ->all();

        return [
            'key' => 'cms-poll-'.$content->id,
            'question' => $this->media->metadata($content, 'question', $content->title),
            'options' => $options,
            'votes' => $this->media->metadata($content, 'votes', 'CMS poll'),
        ];
    }
}
