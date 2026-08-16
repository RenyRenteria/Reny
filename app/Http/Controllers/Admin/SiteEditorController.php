<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\CommunityPostReply;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\Admin\VideoCatalogService;
use App\Services\CmsPreviewContext;
use App\Services\Commerce\ProductCatalog;
use App\Services\CommunityInteractionService;
use App\Services\CommunityMemberDirectory;
use App\Services\CommunityRsvpDirectory;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Services\MusicBannerSettingsService;
use App\Services\PageSettingsService;
use App\Services\PublicCmsContentService;
use App\Services\StorefrontSettingsService;
use App\Support\SiteEditorPageRegistry;
use App\Support\VideoCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteEditorController extends Controller
{
    public function __construct(private readonly SiteEditorPageRegistry $registry) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.site-editor.show', ['page' => 'home']);
    }

    public function show(
        Request $request,
        PublicCmsContentService $cms,
        CommunityMemberDirectory $members,
        CommunityRsvpDirectory $rsvps,
        VideoCatalogService $videoCatalog,
        string $page,
    ): View {
        $pageConfig = $this->registry->page($page);

        abort_unless($pageConfig !== null, 404);

        $usesStorefrontEditor = in_array($page, ['home', 'store'], true);
        $communitySection = $page === 'community' ? $this->communitySection($request) : null;

        return view('admin.site-editor.show', [
            'activePage' => $page,
            'pages' => $this->registry->pages(),
            'pageConfig' => $pageConfig,
            'publicUrl' => url($pageConfig['public_path']),
            'previewUrl' => route('admin.site-editor.preview', ['page' => $page]),
            'publicPayload' => $this->publicPayload($cms, $page),
            'pageSettings' => in_array($page, PageSettingsService::PAGES, true)
                ? app(PageSettingsService::class)->editorPayload($page)
                : null,
            'pageSettingsForm' => in_array($page, PageSettingsService::PAGES, true)
                ? ['mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get()]
                : null,
            'musicBanner' => $page === 'music'
                ? app(MusicBannerSettingsService::class)->editorPayload()
                : null,
            'musicContentForm' => $page === 'music'
                ? $this->musicContentFormData()
                : null,
            'videoContentForm' => $page === 'videos'
                ? $this->videoContentFormData($videoCatalog)
                : null,
            'storefront' => $usesStorefrontEditor
                ? app(StorefrontSettingsService::class)->editorPayload()
                : null,
            'storefrontForm' => $usesStorefrontEditor
                ? $this->storefrontFormData()
                : null,
            'communitySection' => $communitySection,
            'communityMembers' => $page === 'community' && $communitySection === 'members'
                ? $this->communityMemberData($request, $members)
                : null,
            'communityRsvps' => $page === 'community' && $communitySection === 'rsvp'
                ? $this->communityRsvpData($request, $rsvps)
                : null,
            'communityPostForm' => $page === 'community' && $communitySection === 'post'
                ? $this->communityPostFormData($request)
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
            'trackOptions' => $this->musicTrackOptions(),
            'contents' => $this->manageableMusicContents(),
            'defaultType' => ContentType::MusicalAlbum->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function videoContentFormData(VideoCatalogService $catalog): array
    {
        $contents = $catalog->contents();
        $catalogContents = $contents->reject(
            fn (EditorialContent $content): bool => VideoCatalog::isFeaturedOnly($content)
        );
        $featureCandidates = $contents->filter(
            fn (EditorialContent $content): bool => $catalog->canFeature($content)
        );

        return [
            'contents' => $contents,
            'catalogContents' => $catalogContents,
            'featured' => $featureCandidates->first(
                fn (EditorialContent $content): bool => VideoCatalog::isFeatured($content)
            ) ?? $featureCandidates->first(),
            'featureCandidates' => $featureCandidates,
            'groups' => VideoCatalog::groups(),
            'grouped' => $catalogContents->groupBy(
                fn (EditorialContent $content): string => VideoCatalog::groupFor($content)
            ),
            'publishedCount' => $catalogContents->where('status', EditorialStatus::Published)->count(),
            'draftCount' => $catalogContents->where('status', EditorialStatus::Draft)->count(),
            'scheduledCount' => $catalogContents->where('status', EditorialStatus::Scheduled)->count(),
            'visibilityAudiences' => VisibilityAudience::cases(),
        ];
    }

    public function updateVideoOrder(Request $request, VideoCatalogService $catalog): RedirectResponse
    {
        $validated = $request->validate([
            'video_ids' => ['required', 'array', 'max:300'],
            'video_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $catalog->reorder($request->user(), array_map('intval', $validated['video_ids']));

        return redirect()
            ->route('admin.site-editor.show', ['page' => 'videos'])
            ->with('status', 'Orden del catálogo de videos actualizado.');
    }

    public function updateFeaturedVideo(
        Request $request,
        VideoCatalogService $catalog,
    ): RedirectResponse {
        $validated = $request->validate([
            'video_id' => [
                'required',
                'integer',
                Rule::exists('editorial_contents', 'id')->where(
                    fn ($query) => $query
                        ->where('type', ContentType::Video->value)
                        ->where('status', EditorialStatus::Published->value)
                ),
            ],
        ]);
        $video = EditorialContent::query()->findOrFail($validated['video_id']);
        $catalog->setFeatured($request->user(), $video);

        return redirect()
            ->route('admin.site-editor.show', ['page' => 'videos'])
            ->with('status', sprintf('"%s" ahora es el video destacado.', $video->title));
    }

    /**
     * @return Collection<int, EditorialContent>
     */
    private function manageableMusicContents(): Collection
    {
        return EditorialContent::query()
            ->with([
                'mediaAssets' => fn ($query) => $query->orderBy('content_media_assets.sort_order'),
                'releaseWindows',
            ])
            ->whereIn('type', [
                ContentType::Song->value,
                ContentType::MusicalAlbum->value,
                ContentType::MusicPlaylist->value,
            ])
            ->orderByRaw("CASE type WHEN 'song' THEN 0 WHEN 'musical_album' THEN 1 WHEN 'music_playlist' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(150)
            ->get();
    }

    /**
     * @return array<int, array{value: string, label: string, group: string}>
     */
    private function musicTrackOptions(): array
    {
        return EditorialContent::query()
            ->whereIn('type', [ContentType::Song->value, ContentType::MusicalAlbum->value])
            ->latest()
            ->limit(100)
            ->get()
            ->flatMap(function (EditorialContent $content) {
                if ($content->type === ContentType::Song) {
                    return [[
                        'value' => 'song:'.$content->id,
                        'label' => $content->title,
                        'group' => 'Singles',
                    ]];
                }

                $tracks = collect($content->metadata['tracks'] ?? []);

                if ($tracks->isEmpty() && filled($content->metadata['tracklist'] ?? null)) {
                    $tracks = collect(preg_split('/\R/', (string) $content->metadata['tracklist']) ?: [])
                        ->map(fn (string $trackName): array => ['track_name' => trim($trackName)])
                        ->filter(fn (array $track): bool => filled($track['track_name']));
                }

                return $tracks
                    ->values()
                    ->map(fn (array $track, int $index): array => [
                        'value' => 'album:'.$content->id.':'.$index,
                        'label' => $content->title.' - '.($track['track_name'] ?? 'Track '.($index + 1)),
                        'group' => 'Album tracks',
                    ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function storefrontFormData(): array
    {
        $storeContents = EditorialContent::query()
            ->whereIn('type', [
                ContentType::Product->value,
                ContentType::Event->value,
                ContentType::Drop->value,
                ContentType::Exclusive->value,
                ContentType::MusicalAlbum->value,
                ContentType::DeluxeAlbum->value,
            ])
            ->where('status', '!=', 'archived')
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(150)
            ->get();

        return [
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get(),
            'storeContents' => $storeContents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communityRsvpData(Request $request, CommunityRsvpDirectory $directory): array
    {
        $data = $directory->data((string) $request->query('rsvp_event'));

        return [
            ...$data,
            'export_url' => $data['selected_event_key'] === ''
                ? null
                : route('admin.site-editor.community-rsvps.export', ['event' => $data['selected_event_key']]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communityMemberData(Request $request, CommunityMemberDirectory $directory): array
    {
        $filters = $directory->filters($request);
        $members = $directory->query($filters['search'], $filters['plan'])
            ->latest('created_at')
            ->paginate(50, ['*'], 'members_page')
            ->withQueryString();

        return [
            ...$filters,
            'members' => $members,
            'directory' => $directory,
            'export_url' => route('admin.site-editor.community-members.export', array_filter([
                'member_search' => $filters['search'],
                'member_plan' => $filters['plan'] === CommunityMemberDirectory::PLAN_ALL ? null : $filters['plan'],
            ])),
        ];
    }

    private function communitySection(Request $request): string
    {
        $section = trim((string) $request->query('community_section'));

        if ($section === '' && $request->has('rsvp_event')) {
            return 'rsvp';
        }

        if ($section === '' && ($request->has('member_search') || $request->has('member_plan'))) {
            return 'members';
        }

        return in_array($section, ['post', 'members', 'rsvp'], true) ? $section : 'post';
    }

    /**
     * @return array<string, mixed>
     */
    private function communityPostFormData(Request $request): array
    {
        $posts = EditorialContent::query()
            ->where('type', ContentType::Post->value)
            ->with(['mediaAssets', 'createdBy:id,name,email'])
            ->orderByRaw("CASE status WHEN 'scheduled' THEN 0 WHEN 'published' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(100)
            ->get();
        $postKeys = $posts->map(fn (EditorialContent $post): string => 'cms-post-'.$post->id);
        $comments = CommunityPostReply::query()
            ->whereIn('post_key', $postKeys)
            ->with('user:id,name,username,email')
            ->latest()
            ->limit(250)
            ->get();

        return [
            'can_manage' => $request->user()?->canManageCommunityPosts() ?? false,
            'editor_email' => config('admin.community_editor_email'),
            'posts' => $posts,
            'comments' => $comments,
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
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

    public function updatePageSettings(
        Request $request,
        PageSettingsService $settings,
        MediaLibraryService $library,
        string $page,
    ): RedirectResponse {
        abort_unless(in_array($page, PageSettingsService::PAGES, true), 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['draft', 'publish'])],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'cover_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where(fn ($query) => $query
                    ->whereIn('type', [MediaAssetType::Image->value, MediaAssetType::Thumbnail->value])
                    ->where('processing_status', 'ready')
                    ->where('is_public', true)),
            ],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'],
            'cover_alt' => ['nullable', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:160'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'url:http,https', 'max:2048'],
            'twitter_card' => ['nullable', Rule::in(['summary', 'summary_large_image'])],
            'twitter_title' => ['nullable', 'string', 'max:160'],
            'twitter_description' => ['nullable', 'string', 'max:320'],
            'twitter_image' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $status = $validated['action'] === 'publish'
            ? SitePageSetting::STATUS_PUBLISHED
            : SitePageSetting::STATUS_DRAFT;

        if ($status === SitePageSetting::STATUS_PUBLISHED && ! $request->user()?->canPublishContent()) {
            abort(403);
        }

        try {
            $file = $request->file('cover');

            if ($file instanceof UploadedFile) {
                $asset = $library->storeUploads($request->user(), [
                    'type' => MediaAssetType::Image->value,
                    'title' => str($page)->headline()->toString().' page cover',
                    'alt_text' => $validated['cover_alt'] ?? str($page)->headline()->toString().' cover',
                    'is_public' => true,
                ], [$file])->first();
                $validated['cover_asset_id'] = $asset?->id;
            }
        } catch (MediaUploadException $exception) {
            return back()->withErrors(['cover' => $exception->getMessage()])->withInput();
        }

        unset($validated['action'], $validated['cover']);
        $settings->save($request->user(), $page, $validated, $status);

        return redirect()
            ->route('admin.site-editor.show', ['page' => $page])
            ->with('status', $status === SitePageSetting::STATUS_PUBLISHED
                ? str($page)->headline()->toString().' page settings published.'
                : str($page)->headline()->toString().' page settings draft saved.');
    }

    public function preview(
        Request $request,
        PublicCmsContentService $cms,
        CommunityInteractionService $community,
        CmsPreviewContext $previewContext,
        string $page,
    ): Response {
        $pageConfig = $this->registry->page($page);

        abort_unless($pageConfig !== null, 404);

        $audience = VisibilityAudience::tryFrom((string) $request->query('audience')) ?? VisibilityAudience::Open;
        $request->attributes->set('site_editor_preview_audience', $audience->value);
        $request->attributes->set('site_editor_guest_preview', $audience === VisibilityAudience::Open);

        return $previewContext->run($audience, function () use ($audience, $cms, $community, $page, $previewContext): Response {
            $preview = match ($page) {
                'home' => view('home', [
                    'publicCms' => $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'rsvpTickets' => [],
                    'previewAudience' => $audience->value,
                ]),
                'music' => view('welcome', [
                    'publicCms' => $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'previewAudience' => $audience->value,
                ]),
                'videos' => view('videos', [
                    'publicCms' => $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'previewAudience' => $audience->value,
                ]),
                'photos' => view('photos', [
                    'publicCms' => $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'previewAudience' => $audience->value,
                ]),
                'store' => view('store', [
                    'publicCms' => $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'rsvpTickets' => [],
                    'storePage' => 'store',
                    'previewAudience' => $audience->value,
                ]),
                'community' => view('community', [
                    'publicCms' => $communityPayload = $this->publicPayload($cms, $page, $previewContext->viewer()),
                    'community' => $community->viewModel($previewContext->viewer(), $communityPayload),
                    'previewAudience' => $audience->value,
                ]),
            };

            return response($preview->render())
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->header('Cache-Control', 'no-store, private');
        });
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
                        'status_label' => 'Page settings',
                        'status_tone' => 'success',
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
            $rules["slots.{$slotKey}.url"] = ['nullable', 'string', 'max:2048'];
            $rules["slots.{$slotKey}.image_asset_id"] = [
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where(fn ($query) => $query
                    ->whereIn('type', [MediaAssetType::Image->value, MediaAssetType::Thumbnail->value])
                    ->where('processing_status', 'ready')
                    ->where('is_public', true)),
            ];
            $rules["slots.{$slotKey}.content_id"] = ['nullable', 'integer', 'exists:editorial_contents,id'];
            $rules["slot_images.{$slotKey}"] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:51200'];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request): void {
            if ($request->input('action') !== 'publish') {
                return;
            }

            $products = app(ProductCatalog::class);

            foreach (StorefrontSettingsService::slotKeys() as $slotKey) {
                $slot = (array) $request->input("slots.{$slotKey}", []);
                $actionType = (string) ($slot['action_type'] ?? '');
                $productKey = trim((string) ($slot['product_key'] ?? ''));
                $url = trim((string) ($slot['url'] ?? ''));

                if (blank($slot['title'] ?? null) || blank($slot['cta_label'] ?? null)) {
                    $validator->errors()->add("slots.{$slotKey}.title", 'Published cards require a title and CTA label.');
                }

                if ($actionType === 'buy' && empty($slot['content_id']) && ($productKey === '' || ! $products->find($productKey))) {
                    $validator->errors()->add("slots.{$slotKey}.product_key", 'Select an active product with a valid checkout before publishing.');
                }

                if (
                    $actionType === 'link'
                    && $url === ''
                    && empty($slot['content_id'])
                ) {
                    $validator->errors()->add("slots.{$slotKey}.url", 'Link cards require a destination URL or linked content.');
                }

                if ($url !== '' && ! $this->isSafeActionUrl($url)) {
                    $validator->errors()->add("slots.{$slotKey}.url", 'Use a full HTTP(S) URL or an internal path beginning with /.');
                }

                if (! empty($slot['content_id'])) {
                    $content = EditorialContent::query()->find((int) $slot['content_id']);
                    $allowedTypes = match ($slotKey) {
                        'album' => [ContentType::MusicalAlbum, ContentType::DeluxeAlbum],
                        'event_primary', 'event_secondary' => [ContentType::Event],
                        default => [ContentType::Product, ContentType::Drop, ContentType::Exclusive],
                    };

                    if (! $content || ! in_array($content->type, $allowedTypes, true)) {
                        $validator->errors()->add("slots.{$slotKey}.content_id", 'Linked content does not match this storefront slot.');
                    } elseif (! in_array($content->status->value, ['published', 'scheduled'], true)) {
                        $validator->errors()->add("slots.{$slotKey}.content_id", 'Publish or schedule linked content before publishing the Store.');
                    } elseif (
                        ($content->type !== ContentType::Event || data_get($content->metadata, 'ticketing_mode') !== 'rsvp')
                        && (string) data_get($content->metadata, 'action_type', $actionType) === 'buy'
                        && $products->forContent($content) === null
                    ) {
                        $validator->errors()->add("slots.{$slotKey}.content_id", 'Linked content needs an active checkout key, price, currency, inventory and availability.');
                    }
                }
            }
        });

        $validated = $validator->validate();

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

    private function isSafeActionUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
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
