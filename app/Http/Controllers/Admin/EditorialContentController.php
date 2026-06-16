<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Support\EditorialContentForms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EditorialContentController extends Controller
{
    private const SCHEDULING_TIMEZONE = 'America/Panama';

    public function __construct(private readonly EditorialContentForms $forms) {}

    public function index(Request $request): View
    {
        $selectedType = ContentType::tryFrom((string) $request->query('type')) ?? ContentType::Post;

        return view('admin.editorial.index', $this->viewData($selectedType));
    }

    public function edit(EditorialContent $content): View
    {
        $content->load(['mediaAssets', 'releaseWindows']);

        return view('admin.editorial.index', $this->viewData($content->type, $content));
    }

    public function preview(EditorialContent $content): Response
    {
        $content->load(['mediaAssets', 'releaseWindows', 'taxonomies']);

        return response()
            ->view('admin.editorial.preview', [
                'content' => $content,
                'panamaTimezone' => self::SCHEDULING_TIMEZONE,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(ContentType $selectedType, ?EditorialContent $content = null): array
    {
        $contentItems = EditorialContent::query()
            ->with(['mediaAssets'])
            ->latest()
            ->limit(20)
            ->get();

        return [
            'forms' => $this->forms->definitions(),
            'selectedType' => $selectedType,
            'selectedDefinition' => $this->forms->definition($selectedType),
            'content' => $content,
            'contents' => $contentItems,
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(30)->get(),
            'selectedMediaIds' => $content?->mediaAssets->pluck('id')->all() ?? [],
            'releaseWindows' => $this->releaseWindowDefaults($content),
            'panamaTimezone' => self::SCHEDULING_TIMEZONE,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function releaseWindowDefaults(?EditorialContent $content): array
    {
        $windows = [
            'member' => ['audience' => 'member', 'starts_at' => null, 'ends_at' => null],
            'open' => ['audience' => 'open', 'starts_at' => null, 'ends_at' => null],
        ];

        if ($content === null) {
            return $windows;
        }

        foreach ($content->releaseWindows as $window) {
            if (! array_key_exists($window->audience->value, $windows)) {
                continue;
            }

            $windows[$window->audience->value] = [
                'audience' => $window->audience->value,
                'starts_at' => $window->starts_at?->timezone(self::SCHEDULING_TIMEZONE)->format('Y-m-d\TH:i'),
                'ends_at' => $window->ends_at?->timezone(self::SCHEDULING_TIMEZONE)->format('Y-m-d\TH:i'),
            ];
        }

        return $windows;
    }
}
