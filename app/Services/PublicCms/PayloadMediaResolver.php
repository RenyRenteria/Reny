<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use Illuminate\Support\Collection;

class PayloadMediaResolver
{
    /**
     * @param  array<int, string>  $metadataKeys
     */
    public function mediaUrl(EditorialContent $content, array $metadataKeys): ?string
    {
        $assetId = collect($metadataKeys)
            ->map(fn (string $key): mixed => $this->metadata($content, $key))
            ->filter()
            ->first();

        $assets = $content->mediaAssets->filter(fn (MediaAsset $asset): bool => in_array(
            $asset->type,
            [MediaAssetType::Image, MediaAssetType::Thumbnail],
            true,
        ));
        $asset = $assets
            ->when($assetId, fn (Collection $assets): Collection => $assets->where('id', (int) $assetId))
            ->first();

        if (! $asset instanceof MediaAsset) {
            $asset = $assets->first();
        }

        return $asset?->publicUrl();
    }

    public function audioUrl(EditorialContent $content): ?string
    {
        foreach (['audio_url', 'preview_audio_url', 'external_audio_url', 'stream_url', 'preview_url'] as $key) {
            $value = trim((string) $this->metadata($content, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        $assetId = $this->metadata($content, 'audio_asset_id')
            ?: collect($this->metadata($content, 'tracks', []))
                ->pluck('track_audio_asset_id')
                ->filter()
                ->first();

        $asset = $content->mediaAssets
            ->when($assetId, fn (Collection $assets): Collection => $assets->where('id', (int) $assetId))
            ->first(fn (MediaAsset $asset): bool => $asset->type === MediaAssetType::Audio);

        return $asset?->publicUrl();
    }

    public function audioAssetUrl(EditorialContent $content, mixed $assetId): ?string
    {
        if (blank($assetId)) {
            return null;
        }

        $asset = $content->mediaAssets
            ->where('id', (int) $assetId)
            ->first(fn (MediaAsset $asset): bool => $asset->type === MediaAssetType::Audio);

        return $asset?->publicUrl();
    }

    /**
     * @return array<int, string>
     */
    public function tracklist(EditorialContent $content): array
    {
        $tracks = collect($this->metadata($content, 'tracks', []))
            ->map(fn (mixed $track): string => is_array($track) ? trim((string) ($track['track_name'] ?? '')) : trim((string) $track))
            ->filter()
            ->values();

        if ($tracks->isNotEmpty()) {
            return $tracks->all();
        }

        return collect(preg_split('/\R/', (string) $this->metadata($content, 'tracklist', '')) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    public function albumTrackCount(EditorialContent $content): int
    {
        $explicitCount = $this->metadata($content, 'track_count');

        if (is_numeric($explicitCount) && (int) $explicitCount > 0) {
            return (int) $explicitCount;
        }

        return count($this->tracklist($content));
    }

    /**
     * @return array<int, string>
     */
    public function playlistTracks(EditorialContent $content): array
    {
        return collect($this->metadata($content, 'tracks', []))
            ->map(fn (mixed $reference): ?string => is_string($reference) ? $this->playlistTrackLabel($reference) : null)
            ->filter()
            ->values()
            ->all();
    }

    public function metadata(EditorialContent $content, string $key, mixed $default = null): mixed
    {
        return data_get($content->metadata ?? [], $key, $default);
    }

    public function youtubeId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            return $this->validYoutubeId(explode('/', $path)[0] ?? null);
        }

        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        if (isset($query['v'])) {
            return $this->validYoutubeId((string) $query['v']);
        }

        if (preg_match('#^(?:shorts|embed)/([^/]+)#', $path, $matches) === 1) {
            return $this->validYoutubeId($matches[1]);
        }

        return null;
    }

    public function youtubePlaylistId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be'], true)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $playlistId = trim((string) ($query['list'] ?? ''));

        return preg_match('/^[A-Za-z0-9_-]{6,80}$/', $playlistId) === 1 ? $playlistId : null;
    }

    private function validYoutubeId(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $value) === 1 ? $value : null;
    }

    public function availability(EditorialContent $content): string
    {
        $inventory = $this->metadata($content, 'inventory');

        if (is_numeric($inventory)) {
            return ((int) $inventory).' available';
        }

        return 'Available now';
    }

    private function playlistTrackLabel(string $reference): ?string
    {
        $parts = explode(':', $reference);

        if (count($parts) < 2) {
            return null;
        }

        if ($parts[0] === 'song') {
            return EditorialContent::query()
                ->whereKey((int) $parts[1])
                ->where('type', ContentType::Song->value)
                ->value('title');
        }

        if ($parts[0] !== 'album' || count($parts) !== 3) {
            return null;
        }

        $album = EditorialContent::query()
            ->whereKey((int) $parts[1])
            ->where('type', ContentType::MusicalAlbum->value)
            ->first();

        if (! $album) {
            return null;
        }

        $index = (int) $parts[2];
        $trackName = data_get($album->metadata ?? [], "tracks.{$index}.track_name");

        if (blank($trackName)) {
            $trackName = collect(preg_split('/\R/', (string) data_get($album->metadata ?? [], 'tracklist', '')) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values()
                ->get($index);
        }

        return filled($trackName) ? $album->title.' - '.$trackName : null;
    }
}
