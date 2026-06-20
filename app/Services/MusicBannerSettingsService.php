<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

class MusicBannerSettingsService
{
    public const PAGE = 'music';

    public const SECTION = 'banner';

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return [
            'eyebrow_line_1' => 'First',
            'eyebrow_line_2' => 'Album',
            'title_line_1' => 'Biggest',
            'title_line_2' => 'Launch',
            'subtitle' => 'Comeback Album!',
            'description' => 'A cinematic release package for Reny Renteria, built around a lead album, featured tracks, fan updates, and premium music drops.',
            'footer_line_1' => 'Visit us today at',
            'footer_line_2' => 'renyrenteria.com',
            'badge' => 'RR',
            'destination_url' => 'https://renyrenteria.com',
            'sticker_line_1' => 'THE FIRST ALBUM',
            'sticker_line_2' => 'BANO #1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editorPayload(): array
    {
        $draft = $this->draftSetting();
        $published = $this->publishedSetting();
        $active = $draft ?? $published;

        return [
            ...$this->payloadFor($active),
            '_editor_status' => $draft ? SitePageSetting::STATUS_DRAFT : ($published ? SitePageSetting::STATUS_PUBLISHED : SitePageSetting::STATUS_DRAFT),
            '_has_draft' => $draft !== null,
            '_has_published' => $published !== null,
            '_published_at' => $published?->published_at,
            '_updated_at' => $active?->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            ...$this->payloadFor($this->publishedSetting()),
            '_editor_status' => SitePageSetting::STATUS_PUBLISHED,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(User $actor, array $payload, ?MediaAsset $mediaAsset, string $status): SitePageSetting
    {
        $status = $status === SitePageSetting::STATUS_PUBLISHED
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;

        $attributes = [
            'payload' => $this->normalize($payload),
            'media_asset_id' => $mediaAsset?->id,
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
            'page' => self::PAGE,
            'section' => self::SECTION,
            'status' => $status,
        ], $attributes);

        if ($status === SitePageSetting::STATUS_PUBLISHED) {
            $this->draftSetting()?->delete();
        }

        return $setting->fresh(['mediaAsset']);
    }

    public function draftSetting(): ?SitePageSetting
    {
        return SitePageSetting::query()
            ->with('mediaAsset')
            ->forSection(self::PAGE, self::SECTION)
            ->draft()
            ->first();
    }

    public function publishedSetting(): ?SitePageSetting
    {
        return SitePageSetting::query()
            ->with('mediaAsset')
            ->forSection(self::PAGE, self::SECTION)
            ->published()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(?SitePageSetting $setting): array
    {
        $asset = $setting?->mediaAsset;

        return [
            ...$this->normalize($setting?->payload ?? []),
            'image_asset_id' => $asset?->id,
            'image_url' => $asset?->publicUrl(),
            'image_alt' => $asset?->alt_text ?: 'Reny Renteria music banner artwork',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function normalize(array $payload): array
    {
        $defaults = $this->defaults();

        return collect($defaults)
            ->mapWithKeys(function (string $default, string $key) use ($payload): array {
                $value = $payload[$key] ?? $default;

                return [$key => is_scalar($value) ? trim((string) $value) : $default];
            })
            ->all();
    }
}
