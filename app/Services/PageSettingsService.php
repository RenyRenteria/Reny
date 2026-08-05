<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PageSettingsService
{
    public const SECTION = 'page_settings';

    /** @var array<int, string> */
    public const PAGES = ['videos', 'photos', 'community', 'store'];

    public function __construct(private readonly CmsPreviewContext $previewContext) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $page): array
    {
        $defaults = [
            'videos' => [
                'eyebrow' => "Video\nPremiere",
                'title' => "Watch\nNow",
                'subtitle' => 'Official Music Videos',
                'description' => 'Official releases, live performances, playlists, behind the scenes clips, and vlogs in one place.',
            ],
            'photos' => [
                'eyebrow' => 'Photos',
                'title' => 'Visual archive',
                'subtitle' => 'Albums and moments',
                'description' => 'Official photos, backstage moments, campaigns, and member-only visual stories.',
            ],
            'community' => [
                'eyebrow' => 'Comunidad oficial',
                'title' => 'Directo de Reny. Cerca de la comunidad.',
                'subtitle' => 'Posts, polls and live conversation',
                'description' => 'Posts oficiales, anuncios y momentos exclusivos de Reny. La conversación continúa en el Live Chat.',
            ],
            'store' => [
                'eyebrow' => 'Official Store',
                'title' => 'Shows and releases',
                'subtitle' => 'Tickets, music and limited products',
                'description' => 'Buy directly from the same catalog used by checkout.',
            ],
        ];

        abort_unless(in_array($page, self::PAGES, true), 404);

        $pageDefaults = $defaults[$page];
        $title = str_replace("\n", ' ', $pageDefaults['title']).' | Reny Renteria';

        return [
            ...$pageDefaults,
            'cover_asset_id' => null,
            'cover_url' => null,
            'cover_alt' => str_replace("\n", ' ', $pageDefaults['title']),
            'meta_title' => $title,
            'meta_description' => $pageDefaults['description'],
            'canonical_url' => url('/'.($page === 'community' ? 'royals' : $page)),
            'og_title' => $title,
            'og_description' => $pageDefaults['description'],
            'og_image' => null,
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $title,
            'twitter_description' => $pageDefaults['description'],
            'twitter_image' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editorPayload(string $page): array
    {
        $draft = $this->setting($page, SitePageSetting::STATUS_DRAFT);
        $published = $this->setting($page, SitePageSetting::STATUS_PUBLISHED);
        $active = $draft ?? $published;

        return [
            ...$this->payloadFor($page, $active),
            '_editor_status' => $draft ? SitePageSetting::STATUS_DRAFT : ($published ? SitePageSetting::STATUS_PUBLISHED : SitePageSetting::STATUS_DRAFT),
            '_published_at' => $published?->published_at,
            '_updated_at' => $active?->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(string $page): array
    {
        $setting = $this->previewContext->active()
            ? ($this->setting($page, SitePageSetting::STATUS_DRAFT)
                ?? $this->setting($page, SitePageSetting::STATUS_PUBLISHED))
            : $this->setting($page, SitePageSetting::STATUS_PUBLISHED);

        return $this->payloadFor($page, $setting);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(User $actor, string $page, array $payload, string $status): SitePageSetting
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        $status = $status === SitePageSetting::STATUS_PUBLISHED
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;
        $attributes = [
            'payload' => $this->normalize($page, $payload),
            'media_asset_id' => $this->assetId($payload['cover_asset_id'] ?? null),
            'updated_by_id' => $actor->id,
        ];

        if ($status === SitePageSetting::STATUS_PUBLISHED) {
            $attributes = [
                ...$attributes,
                'published_by_id' => $actor->id,
                'published_at' => Carbon::now(),
            ];
        }

        $setting = SitePageSetting::query()->updateOrCreate([
            'page' => $page,
            'section' => self::SECTION,
            'status' => $status,
        ], $attributes);

        if ($status === SitePageSetting::STATUS_PUBLISHED) {
            $this->setting($page, SitePageSetting::STATUS_DRAFT)?->delete();
        }

        return $setting->fresh();
    }

    private function setting(string $page, string $status): ?SitePageSetting
    {
        if (! Schema::hasTable('site_page_settings')) {
            return null;
        }

        return SitePageSetting::query()
            ->forSection($page, self::SECTION)
            ->where('status', $status)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $page, ?SitePageSetting $setting): array
    {
        $payload = $this->normalize($page, $setting?->payload ?? []);

        if ($page === 'community' && $payload['canonical_url'] === url('/community')) {
            $payload['canonical_url'] = url('/royals');
        }

        $assetId = $this->assetId($payload['cover_asset_id'] ?? $setting?->media_asset_id);
        $asset = $assetId && Schema::hasTable('media_assets')
            ? MediaAsset::query()->ready()->where('is_public', true)->find($assetId)
            : null;
        $coverUrl = $asset?->publicUrl();

        return [
            ...$payload,
            'cover_asset_id' => $asset?->id,
            'cover_url' => $coverUrl,
            'cover_alt' => $asset?->alt_text ?: $payload['cover_alt'],
            'og_image' => $payload['og_image'] ?: $coverUrl,
            'twitter_image' => $payload['twitter_image'] ?: $coverUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(string $page, array $payload): array
    {
        $defaults = $this->defaults($page);

        return collect($defaults)
            ->mapWithKeys(function (mixed $default, string $key) use ($payload): array {
                if ($key === 'cover_asset_id') {
                    return [$key => $this->assetId(Arr::get($payload, $key))];
                }

                $value = Arr::get($payload, $key, $default);

                if (! is_scalar($value)) {
                    return [$key => $default];
                }

                $value = trim((string) $value);

                return [$key => $value === '' ? $default : $value];
            })
            ->all();
    }

    private function assetId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
