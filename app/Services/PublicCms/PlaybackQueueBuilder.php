<?php

namespace App\Services\PublicCms;

use App\Models\EditorialContent;
use App\Models\User;

class PlaybackQueueBuilder
{
    public function __construct(
        private readonly AccessPayloadBuilder $accessPayloads,
        private readonly ContentQuery $contentQuery,
        private readonly PayloadMediaResolver $media,
    ) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function build(EditorialContent $content, ?User $user, ?int $track = null): array
    {
        if (in_array($content->type, ContentQuery::MUSIC_PLAYLIST_TYPES, true)) {
            return $this->playlistPlaybackQueue($content, $user);
        }

        if (in_array($content->type, ContentQuery::MUSIC_ALBUM_TYPES, true)) {
            return $this->albumPlaybackQueue($content, $track);
        }

        $track = $this->singlePlaybackQueueTrack($content);

        return $track === null ? [] : [$track];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function albumPlaybackQueue(EditorialContent $content, ?int $selectedTrack = null): array
    {
        $imageUrl = $this->media->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']);
        $tracks = collect($this->media->metadata($content, 'tracks', []))
            ->values()
            ->map(function (mixed $track, int $index) use ($content, $imageUrl): ?array {
                if (! is_array($track)) {
                    return null;
                }

                $title = trim((string) ($track['track_name'] ?? '')) ?: 'Track '.($index + 1);
                $audioUrl = $this->media->audioAssetUrl($content, data_get($track, 'track_audio_asset_id'))
                    ?: ($index === 0 ? $this->media->audioUrl($content) : null);

                if ($audioUrl === null) {
                    return null;
                }

                return $this->queueTrackPayload(
                    content: $content,
                    id: $content->getKey().':'.$index,
                    title: $content->title.' - '.$title,
                    audioUrl: $audioUrl,
                    imageUrl: $imageUrl,
                    itemType: 'track',
                );
            })
            ->filter()
            ->values()
            ->all();

        if ($selectedTrack !== null) {
            $selectedId = $content->getKey().':'.$selectedTrack;
            $selected = collect($tracks)->firstWhere('id', $selectedId);

            if (! is_array($selected)) {
                return [];
            }

            return collect($tracks)
                ->reject(fn (array $track): bool => $track['id'] === $selectedId)
                ->prepend($selected)
                ->values()
                ->all();
        }

        if ($tracks !== []) {
            return $tracks;
        }

        $track = $this->singlePlaybackQueueTrack(
            content: $content,
            title: $content->title,
            imageUrl: $imageUrl,
            itemType: 'album',
        );

        return $track === null ? [] : [$track];
    }

    private function singlePlaybackQueueTrack(
        EditorialContent $content,
        ?string $title = null,
        ?string $imageUrl = null,
        string $itemType = 'single',
    ): ?array {
        $audioUrl = $this->media->audioUrl($content);

        if ($audioUrl === null) {
            return null;
        }

        return $this->queueTrackPayload(
            content: $content,
            id: (string) $content->getKey(),
            title: $title ?: $content->title,
            audioUrl: $audioUrl,
            imageUrl: $imageUrl ?: $this->media->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            itemType: $itemType,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function playlistPlaybackQueue(EditorialContent $content, ?User $user): array
    {
        return collect($this->media->metadata($content, 'tracks', []))
            ->map(fn (mixed $reference): ?array => is_string($reference)
                ? $this->playlistPlaybackTrack($reference, $user)
                : null)
            ->filter()
            ->values()
            ->all();
    }

    private function playlistPlaybackTrack(string $reference, ?User $user): ?array
    {
        $parts = explode(':', $reference);

        if (count($parts) < 2) {
            return null;
        }

        if ($parts[0] === 'song') {
            $song = $this->contentQuery->playbackReferenceContent((int) $parts[1], ContentQuery::MUSIC_SINGLE_TYPES);

            if (! $song || ! $this->canPlayReferencedMusic($song, $user)) {
                return null;
            }

            return $this->singlePlaybackQueueTrack($song);
        }

        if ($parts[0] !== 'album' || count($parts) !== 3) {
            return null;
        }

        $album = $this->contentQuery->playbackReferenceContent((int) $parts[1], ContentQuery::MUSIC_ALBUM_TYPES);

        if (! $album || ! $this->canPlayReferencedMusic($album, $user)) {
            return null;
        }

        $index = (int) $parts[2];
        $track = data_get($album->metadata ?? [], "tracks.{$index}");

        if (! is_array($track)) {
            return null;
        }

        $title = trim((string) ($track['track_name'] ?? '')) ?: 'Track '.($index + 1);
        $audioUrl = $this->media->audioAssetUrl($album, data_get($track, 'track_audio_asset_id'));

        if ($audioUrl === null) {
            return null;
        }

        return $this->queueTrackPayload(
            content: $album,
            id: $album->getKey().':'.$index,
            title: $album->title.' - '.$title,
            audioUrl: $audioUrl,
            imageUrl: $this->media->mediaUrl($album, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            itemType: 'track',
        );
    }

    private function canPlayReferencedMusic(EditorialContent $content, ?User $user): bool
    {
        return $this->contentQuery->isPubliclyListable($content)
            && ($this->accessPayloads->music($content, $user)['state'] ?? null) === 'ready';
    }

    /**
     * @return array<string, string>
     */
    private function queueTrackPayload(
        EditorialContent $content,
        string $id,
        string $title,
        string $audioUrl,
        ?string $imageUrl,
        string $itemType,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'audio_url' => $audioUrl,
            'image_url' => $imageUrl ?: '',
            'detail_url' => $this->musicDetailUrl($content),
            'item_type' => $itemType,
            'state' => 'ready',
            'access_label' => '',
            'message' => $title,
        ];
    }

    private function musicDetailUrl(EditorialContent $content): string
    {
        return in_array($content->type, ContentQuery::MUSIC_ALBUM_TYPES, true)
            ? route('music.albums.show', $content)
            : route('public.content.show', $content);
    }
}
