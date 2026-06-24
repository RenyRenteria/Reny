<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\MusicBannerSettingsService;

class MusicPayloadBuilder
{
    public function __construct(
        private readonly AccessPayloadBuilder $accessPayloads,
        private readonly ContentQuery $contentQuery,
        private readonly MusicBannerSettingsService $musicBannerSettings,
        private readonly PayloadMediaResolver $media,
        private readonly PlaybackQueueBuilder $playbackQueues,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?User $user): array
    {
        $albums = $this->contentQuery->listableMusicContents('albums')
            ->limit(4)
            ->get()
            ->values()
            ->map(fn (EditorialContent $content, int $index): array => $this->album($content, $index, $user))
            ->all();

        $singles = $this->contentQuery->listableMusicContents('singles')
            ->limit(8)
            ->get()
            ->values()
            ->map(fn (EditorialContent $content): array => $this->single($content, $user))
            ->all();

        $playlists = $this->contentQuery->listableMusicContents('playlists')
            ->limit(6)
            ->get()
            ->values()
            ->map(fn (EditorialContent $content): array => $this->playlist($content, $user))
            ->all();

        return [
            'banner' => $this->musicBannerSettings->publicPayload(),
            'albums' => $albums,
            'singles' => $singles,
            'playlists' => $playlists,
            'featured' => $this->featured(
                $this->contentQuery->listableMusicContents('all')->first()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collection(?User $user, string $section): array
    {
        $items = $this->contentQuery->listableMusicContents($section)
            ->limit(48)
            ->get()
            ->values()
            ->map(fn (EditorialContent $content, int $index): array => match ($section) {
                'albums' => $this->album($content, $index, $user),
                'playlists' => $this->playlist($content, $user),
                default => $this->single($content, $user),
            })
            ->all();

        return [
            'section' => $section,
            'items' => $items,
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function playback(EditorialContent $content, ?User $user, ?int $track = null): array
    {
        $content->loadMissing([
            'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
            'releaseWindows',
        ]);

        if (! $this->contentQuery->isMusicContent($content) || ! $this->contentQuery->isPubliclyListable($content)) {
            return [
                'status' => 404,
                'payload' => [
                    'state' => 'playback_error',
                    'message' => 'This music item is not available.',
                ],
            ];
        }

        $access = $this->accessPayloads->music($content, $user);

        if ($access['state'] !== 'ready') {
            return [
                'status' => $access['state'] === 'login_required' ? 401 : 403,
                'payload' => [
                    ...$access,
                    'title' => $content->title,
                    'detail_url' => $this->detailUrl($content),
                ],
            ];
        }

        $queue = $this->playbackQueues->build($content, $user, $track);
        $audioUrl = $queue[0]['audio_url'] ?? $this->media->audioUrl($content);
        $basePayload = $this->base($content, $user);

        if ($audioUrl === null) {
            return [
                'status' => 422,
                'payload' => [
                    ...$basePayload,
                    'state' => 'playback_error',
                    'access_label' => 'Audio unavailable',
                    'message' => match (true) {
                        in_array($content->type, ContentQuery::MUSIC_SINGLE_TYPES, true) => 'This single is published, but its audio source is not connected yet.',
                        in_array($content->type, ContentQuery::MUSIC_PLAYLIST_TYPES, true) => 'This playlist is published, but none of its tracks can be played yet.',
                        default => 'This album is published, but a playable audio source is not connected yet.',
                    },
                    'cta_label' => 'Open details',
                    'cta_url' => $this->detailUrl($content),
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                ...$basePayload,
                'state' => 'ready',
                'access_label' => '',
                'message' => $queue[0]['title'] ?? $content->title,
                'audio_url' => $audioUrl,
                'queue' => $queue,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function albumDetail(EditorialContent $content, ?User $user): array
    {
        $content->loadMissing([
            'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
            'releaseWindows',
        ]);

        abort_unless(in_array($content->type, ContentQuery::MUSIC_ALBUM_TYPES, true), 404);
        abort_unless($this->contentQuery->isPubliclyListable($content), 404);

        $album = $this->album($content, 0, $user);

        return [
            ...$album,
            'track_items' => $this->albumTrackItems($content, $album),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function album(EditorialContent $content, int $index, ?User $user = null): array
    {
        return [
            ...$this->base($content, $user),
            'title' => $content->title,
            'meta' => $this->media->albumTrackCount($content).' tracks',
            'cover_class' => ['cover-a', 'cover-b', 'cover-c', 'cover-d'][$index % 4],
            'image_url' => $this->media->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            'kind' => 'album',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function single(EditorialContent $content, ?User $user = null): array
    {
        return [
            ...$this->base($content, $user),
            'title' => $content->title,
            'image_url' => $this->media->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
            'kind' => 'single',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function playlist(EditorialContent $content, ?User $user = null): array
    {
        $tracks = $this->media->playlistTracks($content);

        return [
            'id' => (string) $content->getKey(),
            'visibility' => $content->visibility->value,
            'summary' => $content->summary ?: '',
            'detail_url' => route('public.content.show', $content),
            'play_url' => route('music.play', $content),
            'title' => $content->title,
            'meta' => count($tracks).' tracks',
            'tracks' => $tracks,
            'image_url' => $this->media->mediaUrl($content, ['playlist_cover_asset_id', 'image_asset_id']),
            'kind' => 'playlist',
            ...$this->accessPayloads->music($content, $user),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function featured(?EditorialContent $content): ?array
    {
        if (! $content instanceof EditorialContent) {
            return null;
        }

        return [
            'eyebrow' => $this->media->metadata($content, 'eyebrow', 'CMS Release'),
            'title' => $content->title,
            'subtitle' => $content->summary ?: $this->media->metadata($content, 'subtitle', 'Latest published drop'),
            'copy' => $content->body ?: $content->summary,
            'url' => route('public.content.show', $content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function base(EditorialContent $content, ?User $user): array
    {
        $access = $this->accessPayloads->music($content, $user);

        return [
            'id' => (string) $content->getKey(),
            'visibility' => $content->visibility->value,
            'summary' => $content->summary ?: $content->body ?: '',
            'detail_url' => $this->detailUrl($content),
            'play_url' => route('music.play', $content),
            'image_url' => match ($content->type) {
                ContentType::Song => $this->media->mediaUrl($content, ['artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
                ContentType::MusicalAlbum, ContentType::DeluxeAlbum => $this->media->mediaUrl($content, ['album_artwork_asset_id', 'cover_asset_id', 'image_asset_id']),
                ContentType::MusicPlaylist => $this->media->mediaUrl($content, ['playlist_cover_asset_id', 'image_asset_id', 'cover_asset_id']),
                default => $this->media->mediaUrl($content, ['image_asset_id', 'cover_asset_id']),
            },
            'tracks' => $content->type === ContentType::MusicPlaylist ? $this->media->playlistTracks($content) : $this->media->tracklist($content),
            'has_audio_source' => $this->media->audioUrl($content) !== null,
            ...$access,
        ];
    }

    /**
     * @param  array<string, mixed>  $album
     * @return array<int, array<string, mixed>>
     */
    private function albumTrackItems(EditorialContent $content, array $album): array
    {
        $structuredTracks = collect($this->media->metadata($content, 'tracks', []))
            ->values()
            ->map(fn (mixed $track, int $index): ?array => is_array($track)
                ? $this->albumTrackItem($content, $album, trim((string) ($track['track_name'] ?? '')) ?: 'Track '.($index + 1), $index)
                : null)
            ->filter()
            ->values();

        if ($structuredTracks->isNotEmpty()) {
            return $structuredTracks->all();
        }

        return collect($this->media->tracklist($content))
            ->values()
            ->map(fn (string $title, int $index): array => $this->albumTrackItem($content, $album, $title, $index))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $album
     * @return array<string, mixed>
     */
    private function albumTrackItem(EditorialContent $content, array $album, string $title, int $index): array
    {
        return [
            'id' => $content->getKey().':'.$index,
            'title' => $title,
            'number' => $index + 1,
            'kind' => 'track',
            'image_url' => $album['image_url'] ?? '',
            'detail_url' => route('music.albums.show', $content),
            'play_url' => route('music.play', ['content' => $content, 'track' => $index]),
            'access_state' => $album['access_state'] ?? 'ready',
            'access_label' => $album['access_label'] ?? 'Open',
            'access_message' => $album['access_message'] ?? 'Ready for this account.',
            'cta_label' => $album['cta_label'] ?? null,
            'cta_url' => $album['cta_url'] ?? null,
        ];
    }

    private function detailUrl(EditorialContent $content): string
    {
        return in_array($content->type, ContentQuery::MUSIC_ALBUM_TYPES, true)
            ? route('music.albums.show', $content)
            : route('public.content.show', $content);
    }
}
