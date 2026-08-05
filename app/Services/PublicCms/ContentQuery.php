<?php

namespace App\Services\PublicCms;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\CmsPreviewContext;
use Illuminate\Database\Eloquent\Builder;

class ContentQuery
{
    public function __construct(private readonly CmsPreviewContext $previewContext) {}

    /**
     * @var array<int, ContentType>
     */
    public const MUSIC_ALBUM_TYPES = [
        ContentType::MusicalAlbum,
    ];

    /**
     * @var array<int, ContentType>
     */
    public const MUSIC_SINGLE_TYPES = [
        ContentType::Song,
        ContentType::Exclusive,
    ];

    /**
     * @var array<int, ContentType>
     */
    public const MUSIC_PLAYLIST_TYPES = [
        ContentType::MusicPlaylist,
    ];

    /**
     * @return array<int, ContentType>
     */
    public function musicTypes(): array
    {
        return [
            ...self::MUSIC_ALBUM_TYPES,
            ...self::MUSIC_SINGLE_TYPES,
            ...self::MUSIC_PLAYLIST_TYPES,
        ];
    }

    /**
     * @param  array<int, ContentType>  $types
     */
    public function visibleContents(?User $user, array $types, ?int $limit = 24): Builder
    {
        $query = EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereIn('type', $this->typeValues($types));

        $this->applyVisibilityConstraint($query, $user);

        return $query
            ->orderByRaw('COALESCE(published_at, scheduled_at, created_at) DESC')
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit));
    }

    public function listableMusicContents(string $section): Builder
    {
        $types = match ($section) {
            'albums' => self::MUSIC_ALBUM_TYPES,
            'singles' => self::MUSIC_SINGLE_TYPES,
            'playlists' => self::MUSIC_PLAYLIST_TYPES,
            default => $this->musicTypes(),
        };

        $query = EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereIn('type', $this->typeValues($types));

        if ($this->previewContext->active()) {
            $this->applyPreviewConstraint($query);
        } else {
            $query->where(fn (Builder $query): Builder => $this->applyPublishedNowConstraint($query));
        }

        return $query
            ->when(
                $section === 'albums',
                fn (Builder $query): Builder => $this->orderByMusicReleaseDate($query),
                fn (Builder $query): Builder => $query->orderByRaw('COALESCE(published_at, scheduled_at, created_at) DESC')
            );
    }

    public function latestAlbumContent(): ?EditorialContent
    {
        return $this->listableMusicContents('albums')->first();
    }

    public function albumContentById(int $id): ?EditorialContent
    {
        if ($id <= 0) {
            return null;
        }

        return $this->listableMusicContents('albums')->whereKey($id)->first();
    }

    /**
     * @param  array<int, ContentType>  $types
     */
    public function playbackReferenceContent(int $id, array $types): ?EditorialContent
    {
        return EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereKey($id)
            ->whereIn('type', $this->typeValues($types))
            ->first();
    }

    public function isMusicContent(EditorialContent $content): bool
    {
        return in_array($content->type, $this->musicTypes(), true);
    }

    public function isPubliclyListable(EditorialContent $content): bool
    {
        if (! in_array($content->status, [EditorialStatus::Published, EditorialStatus::Scheduled], true)) {
            return false;
        }

        $scheduledAt = $content->scheduled_at;

        if ($content->status === EditorialStatus::Published) {
            return $scheduledAt === null || $scheduledAt->lte(now());
        }

        return $scheduledAt !== null && $scheduledAt->lte(now());
    }

    private function orderByMusicReleaseDate(Builder $query): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $releaseDate = match ($driver) {
            'pgsql' => "COALESCE(metadata->>'release_date', metadata->>'release_date_open_view', metadata->>'release_date_member_view')",
            'sqlite' => "COALESCE(json_extract(metadata, '$.release_date'), json_extract(metadata, '$.release_date_open_view'), json_extract(metadata, '$.release_date_member_view'))",
            default => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.release_date')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.release_date_open_view')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.release_date_member_view')))",
        };

        return $query
            ->orderByRaw("{$releaseDate} DESC")
            ->orderByRaw('COALESCE(published_at, scheduled_at, created_at) DESC')
            ->orderByDesc('created_at');
    }

    private function applyPublishedNowConstraint(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereIn('status', [EditorialStatus::Published->value, EditorialStatus::Scheduled->value])
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', EditorialStatus::Published->value)
                            ->where(function (Builder $query) use ($now): void {
                                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', EditorialStatus::Scheduled->value)
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', $now);
                    });
            });
    }

    private function applyVisibilityConstraint(Builder $query, ?User $user): void
    {
        if ($this->previewContext->active()) {
            $this->applyPreviewConstraint($query);

            return;
        }

        $query->visibleFor($user);
    }

    private function applyPreviewConstraint(Builder $query): Builder
    {
        $audience = $this->previewContext->audience() ?? VisibilityAudience::Open;
        $audiences = match ($audience) {
            VisibilityAudience::Open => [VisibilityAudience::Open->value],
            VisibilityAudience::Member => [VisibilityAudience::Open->value, VisibilityAudience::Member->value],
            VisibilityAudience::Royal => [VisibilityAudience::Open->value, VisibilityAudience::Member->value, VisibilityAudience::Royal->value],
            VisibilityAudience::Purchased => VisibilityAudience::values(),
        };

        return $query
            ->where('status', '!=', EditorialStatus::Archived->value)
            ->where(function (Builder $query) use ($audiences): void {
                $query
                    ->where(function (Builder $query) use ($audiences): void {
                        $query->whereDoesntHave('releaseWindows')->whereIn('visibility', $audiences);
                    })
                    ->orWhereHas('releaseWindows', fn (Builder $query): Builder => $query->whereIn('audience', $audiences));
            });
    }

    /**
     * @param  array<int, ContentType>  $types
     * @return array<int, string>
     */
    private function typeValues(array $types): array
    {
        return array_map(fn (ContentType $type): string => $type->value, $types);
    }
}
