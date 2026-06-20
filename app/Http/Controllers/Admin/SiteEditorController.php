<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Services\MusicBannerSettingsService;
use App\Services\PublicCmsContentService;
use App\Services\StorefrontSettingsService;
use App\Support\SiteEditorPageRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteEditorController extends Controller
{
    public function __construct(private readonly SiteEditorPageRegistry $registry) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.site-editor.show', ['page' => 'home']);
    }

    public function show(Request $request, PublicCmsContentService $cms, string $page): View
    {
        $pageConfig = $this->registry->page($page);

        abort_unless($pageConfig !== null, 404);

        $usesStorefrontEditor = in_array($page, ['home', 'store'], true);

        return view('admin.site-editor.show', [
            'activePage' => $page,
            'pages' => $this->registry->pages(),
            'pageConfig' => $pageConfig,
            'publicUrl' => url($pageConfig['public_path']),
            'previewUrl' => route('admin.site-editor.preview', ['page' => $page]),
            'publicPayload' => $this->publicPayload($cms, $page),
            'musicBanner' => $page === 'music'
                ? app(MusicBannerSettingsService::class)->editorPayload()
                : null,
            'musicContentForm' => $page === 'music'
                ? $this->musicContentFormData()
                : null,
            'storefront' => $usesStorefrontEditor
                ? app(StorefrontSettingsService::class)->editorPayload()
                : null,
            'storefrontForm' => $usesStorefrontEditor
                ? $this->storefrontFormData()
                : null,
            'blocks' => $this->blocksFor($pageConfig['blocks']),
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ]);
    }

    /**
     * Data needed by the inline "Add album" / "Add song" screens in the music editor.
     *
     * @return array<string, mixed>
     */
    private function musicContentFormData(): array
    {
        return [
            'visibilityAudiences' => VisibilityAudience::cases(),
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get(),
            'defaultType' => ContentType::MusicalAlbum->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storefrontFormData(): array
    {
        return [
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get(),
            'albums' => EditorialContent::query()
                ->whereIn('type', [ContentType::MusicalAlbum->value, ContentType::DeluxeAlbum->value])
                ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->latest()
                ->limit(100)
                ->get(),
        ];
    }

    public function updateMusicBanner(
        Request $request,
        MusicBannerSettingsService $settings,
        MediaLibraryService $library,
    ): RedirectResponse {
        $validated = $this->validatedMusicBannerPayload($request);
        $status = $validated['action'] === 'publish'
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;

        if ($status === SitePageSetting::STATUS_PUBLISHED && ! $request->user()?->canPublishContent()) {
            abort(403);
        }

        try {
            $mediaAsset = $this->bannerImage($request, $library);
        } catch (MediaUploadException $exception) {
            return back()->withErrors(['image' => $exception->getMessage()])->withInput();
        }

        $settings->save($request->user(), $validated['payload'], $mediaAsset, $status);

        return redirect()
            ->route('admin.site-editor.show', ['page' => 'music'])
            ->with('status', $status === SitePageSetting::STATUS_PUBLISHED
                ? 'Banner de musica publicado en el website.'
                : 'Borrador del banner de musica guardado.');
    }

    public function updateStorefront(
        Request $request,
        StorefrontSettingsService $settings,
        MediaLibraryService $library,
    ): RedirectResponse {
        $validated = $this->validatedStorefrontPayload($request);
        $status = $validated['action'] === 'publish'
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;

        if ($status === SitePageSetting::STATUS_PUBLISHED && ! $request->user()?->canPublishContent()) {
            abort(403);
        }

        try {
            $payload = $this->storefrontPayloadWithUploads($request, $library, $validated['payload']);
        } catch (MediaUploadException $exception) {
            return back()->withErrors(['slot_images' => $exception->getMessage()])->withInput();
        }

        $settings->save($request->user(), $payload, $status);

        $returnPage = $this->storefrontReturnPage($request);

        return redirect()
            ->route('admin.site-editor.show', ['page' => $returnPage])
            ->with('status', $status === SitePageSetting::STATUS_PUBLISHED
                ? str($returnPage)->headline()->toString().' publicado en el website.'
                : 'Borrador de '.str($returnPage)->headline()->lower()->toString().' guardado.');
    }

    public function preview(Request $request, PublicCmsContentService $cms, string $page): View
    {
        $pageConfig = $this->registry->page($page);

        abort_unless($pageConfig !== null, 404);

        $request->attributes->set('site_editor_guest_preview', true);

        return match ($page) {
            'home' => view('home', [
                'publicCms' => $this->publicPayload($cms, $page),
                'rsvpTickets' => [],
            ]),
            'music' => view('welcome', [
                'publicCms' => $this->publicPayload($cms, $page),
            ]),
            'videos' => view('videos', [
                'publicCms' => $this->publicPayload($cms, $page),
            ]),
            'photos' => view('photos', [
                'publicCms' => $this->publicPayload($cms, $page),
            ]),
            'store' => view('store', [
                'publicCms' => $this->publicPayload($cms, $page),
            ]),
            'community' => view('community', [
                'publicCms' => $this->publicPayload($cms, $page),
            ]),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blocksFor(array $blocks): array
    {
        return collect($blocks)
            ->map(function (array $block): array {
                $types = $block['types'] ?? [];

                if ($types === []) {
                    return [
                        ...$block,
                        'contents' => collect(),
                        'counts' => collect(),
                        'status_label' => 'Falta modelo CMS',
                        'status_tone' => 'warning',
                        'create_url' => null,
                    ];
                }

                $contents = $this->latestContentsFor($types);
                $counts = $this->statusCountsFor($types);
                $publishedCount = (int) ($counts->get('published') ?? 0);
                $draftCount = (int) ($counts->get('draft') ?? 0);
                $scheduledCount = (int) ($counts->get('scheduled') ?? 0);

                return [
                    ...$block,
                    'contents' => $contents,
                    'counts' => $counts,
                    'status_label' => match (true) {
                        $publishedCount > 0 => 'Publicado',
                        $scheduledCount > 0 => 'Programado',
                        $draftCount > 0 => 'Borrador',
                        default => 'Falta contenido',
                    },
                    'status_tone' => match (true) {
                        $publishedCount > 0 => 'success',
                        $scheduledCount > 0 => 'info',
                        default => 'warning',
                    },
                    'create_url' => route('admin.content.create', ['type' => $block['default_type']]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, EditorialContent>
     */
    private function latestContentsFor(array $types): Collection
    {
        return EditorialContent::query()
            ->with(['mediaAssets', 'releaseWindows'])
            ->whereIn('type', $types)
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(6)
            ->get();
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<string, int>
     */
    private function statusCountsFor(array $types): Collection
    {
        return EditorialContent::query()
            ->whereIn('type', $types)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (int|string $count): int => (int) $count);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPayload(PublicCmsContentService $cms, string $page, ?User $user = null): array
    {
        if ($page === 'home') {
            return $cms->home($user);
        }

        if ($page === 'music') {
            return $cms->music($user);
        }

        return $cms->payload($page, $user);
    }

    /**
     * @return array{action: string, payload: array<string, mixed>}
     */
    private function validatedMusicBannerPayload(Request $request): array
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['draft', 'publish'])],
            'eyebrow_line_1' => ['nullable', 'string', 'max:40'],
            'eyebrow_line_2' => ['nullable', 'string', 'max:40'],
            'title_line_1' => ['required', 'string', 'max:48'],
            'title_line_2' => ['nullable', 'string', 'max:48'],
            'subtitle' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:260'],
            'footer_line_1' => ['nullable', 'string', 'max:60'],
            'footer_line_2' => ['nullable', 'string', 'max:80'],
            'destination_url' => ['required', 'url:http,https', 'max:2048'],
            'sticker_line_1' => ['nullable', 'string', 'max:40'],
            'sticker_line_2' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in([SitePageSetting::STATUS_DRAFT, SitePageSetting::STATUS_PUBLISHED])],
            'image_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'],
        ]);

        return [
            'action' => $validated['action'],
            'payload' => collect(app(MusicBannerSettingsService::class)->defaults())
                ->mapWithKeys(fn (string $default, string $key): array => [
                    $key => $request->exists($key) ? ($validated[$key] ?? '') : $default,
                ])
                ->all(),
        ];
    }

    /**
     * @return array{action: string, payload: array<string, mixed>}
     */
    private function validatedStorefrontPayload(Request $request): array
    {
        $rules = [
            'action' => ['required', Rule::in(['draft', 'publish'])],
            'royal_pass.copy_before' => ['nullable', 'string', 'max:80'],
            'royal_pass.emphasis' => ['nullable', 'string', 'max:40'],
            'royal_pass.copy_after' => ['nullable', 'string', 'max:140'],
            'royal_pass.cta_label' => ['nullable', 'string', 'max:32'],
            'royal_pass.product_key' => ['nullable', 'string', 'max:80'],
        ];

        foreach (StorefrontSettingsService::slotKeys() as $slotKey) {
            $rules["slots.{$slotKey}.title"] = ['nullable', 'string', 'max:120'];
            $rules["slots.{$slotKey}.eyebrow"] = ['nullable', 'string', 'max:80'];
            $rules["slots.{$slotKey}.description"] = ['nullable', 'string', 'max:260'];
            $rules["slots.{$slotKey}.price_label"] = ['nullable', 'string', 'max:32'];
            $rules["slots.{$slotKey}.cta_label"] = ['nullable', 'string', 'max:40'];
            $rules["slots.{$slotKey}.countdown_at"] = ['nullable', 'date'];
            $rules["slots.{$slotKey}.action_type"] = ['nullable', Rule::in(['buy', 'rsvp', 'link'])];
            $rules["slots.{$slotKey}.product_key"] = ['nullable', 'string', 'max:100'];
            $rules["slots.{$slotKey}.url"] = ['nullable', 'url:http,https', 'max:2048'];
            $rules["slots.{$slotKey}.image_asset_id"] = ['nullable', 'integer', 'exists:media_assets,id'];
            $rules["slots.{$slotKey}.content_id"] = ['nullable', 'integer', 'exists:editorial_contents,id'];
            $rules["slot_images.{$slotKey}"] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'];
        }

        $validated = $request->validate($rules);

        return [
            'action' => $validated['action'],
            'payload' => [
                'royal_pass' => $validated['royal_pass'] ?? [],
                'slots' => $validated['slots'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function storefrontPayloadWithUploads(Request $request, MediaLibraryService $library, array $payload): array
    {
        foreach (StorefrontSettingsService::slotKeys() as $slotKey) {
            $file = $request->file("slot_images.{$slotKey}");

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $asset = $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Image->value,
                'title' => 'Store '.str($slotKey)->headline()->toString().' image',
                'alt_text' => (string) data_get($payload, "slots.{$slotKey}.title", 'Store image'),
                'is_public' => true,
            ], [$file])->first();

            data_set($payload, "slots.{$slotKey}.image_asset_id", $asset?->id);
        }

        return $payload;
    }

    private function storefrontReturnPage(Request $request): string
    {
        $page = (string) $request->input('return_page', 'store');

        return in_array($page, ['home', 'store'], true) ? $page : 'store';
    }

    private function bannerImage(Request $request, MediaLibraryService $library): ?MediaAsset
    {
        $file = $request->file('image');

        if ($file instanceof UploadedFile) {
            return $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Image->value,
                'title' => 'Music banner artwork',
                'alt_text' => 'Reny Renteria music banner artwork',
                'is_public' => true,
            ], [$file])->first();
        }

        $assetId = $request->integer('image_asset_id');

        if ($assetId <= 0) {
            return null;
        }

        return MediaAsset::query()->whereKey($assetId)->first();
    }
}
