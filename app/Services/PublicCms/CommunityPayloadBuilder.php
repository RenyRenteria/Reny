<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Support\CommunityPostContent;
use Carbon\CarbonImmutable;
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
        $poll = $contents->first(function (EditorialContent $content): bool {
            if ($content->type !== ContentType::Poll) {
                return false;
            }

            $closesAt = $this->media->metadata($content, 'closes_at');

            return blank($closesAt) || CarbonImmutable::parse((string) $closesAt)->isFuture();
        });

        $posts = $contents
            ->where('type', ContentType::Post)
            ->values()
            ->map(fn (EditorialContent $content): array => $this->post($content))
            ->sortByDesc('sort_date')
            ->values()
            ->map(function (array $post): array {
                unset($post['sort_date']);

                return $post;
            })
            ->all();

        return [
            'posts' => $posts,
            'poll' => $poll instanceof EditorialContent ? $this->poll($poll) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(EditorialContent $content): array
    {
        $publishedOn = trim((string) $this->media->metadata($content, 'published_on', ''));
        $displayDate = $publishedOn !== ''
            ? CarbonImmutable::parse($publishedOn, config('admin.publishing_timezone', 'America/Panama'))
            : ($content->published_at ?? $content->scheduled_at ?? $content->created_at);

        return [
            'key' => 'cms-post-'.$content->id,
            'title' => $content->title,
            'time' => $displayDate?->format('M j, Y') ?? 'Publicado',
            'sort_date' => $displayDate?->getTimestamp() ?? 0,
            'body_html' => CommunityPostContent::sanitize($content->body ?: $content->summary ?: ''),
            'image_url' => $this->media->mediaUrl($content, ['image_asset_id']),
            'image_alt' => $content->title,
            'media_items' => CommunityPostContent::normalizeMediaUrls(
                Arr::wrap($this->media->metadata($content, 'media_items', []))
            ),
            'comments_enabled' => (bool) $this->media->metadata($content, 'comments_enabled', true),
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
                'votes' => 0,
            ])
            ->all();

        return [
            'content_id' => $content->id,
            'key' => 'cms-poll-'.$content->id,
            'question' => $this->media->metadata($content, 'question', $content->title),
            'options' => $options,
            'votes' => $this->media->metadata($content, 'votes', 'CMS poll'),
            'eligibility' => $this->media->metadata($content, 'eligibility', 'royal'),
            'closes_at' => $this->media->metadata($content, 'closes_at'),
        ];
    }
}
