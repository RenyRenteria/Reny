<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\PublicCmsContentService;
use App\Support\SiteEditorPageRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        return view('admin.site-editor.show', [
            'activePage' => $page,
            'pages' => $this->registry->pages(),
            'pageConfig' => $pageConfig,
            'publicUrl' => url($pageConfig['public_path']),
            'previewUrl' => route('admin.site-editor.preview', ['page' => $page]),
            'publicPayload' => $this->publicPayload($cms, $page),
            'blocks' => $this->blocksFor($pageConfig['blocks']),
            'timezone' => config('admin.publishing_timezone', 'America/Panama'),
        ]);
    }

    public function preview(Request $request, PublicCmsContentService $cms, string $page): View
    {
        $pageConfig = $this->registry->page($page);

        abort_unless($pageConfig !== null, 404);

        $request->attributes->set('site_editor_guest_preview', true);

        return match ($page) {
            'home', 'music' => view('welcome', [
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
        if (in_array($page, ['home', 'music'], true)) {
            return $cms->music($user);
        }

        return $cms->payload($page, $user);
    }
}
