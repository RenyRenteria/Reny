<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicContentController extends Controller
{
    public function show(Request $request, string $type, string $slug): JsonResponse
    {
        if (! in_array($type, ContentType::values(), true)) {
            throw new NotFoundHttpException;
        }

        $content = EditorialContent::query()
            ->with(['mediaAssets', 'releaseWindows', 'taxonomies'])
            ->where('type', $type)
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $this->isLiveContent($content)) {
            throw new NotFoundHttpException;
        }

        if (! $content->isVisibleTo($request->user())) {
            abort(403);
        }

        return response()->json([
            'id' => $content->id,
            'type' => $content->type->value,
            'title' => $content->title,
            'slug' => $content->slug,
            'summary' => $content->summary,
            'body' => $content->body,
            'visibility' => $content->visibility->value,
            'purchase_key' => $content->purchase_key,
            'published_at' => $content->published_at?->toISOString(),
            'metadata' => $content->metadata ?? [],
            'taxonomies' => $content->taxonomies
                ->map(fn ($taxonomy): array => [
                    'type' => $taxonomy->type->value,
                    'name' => $taxonomy->name,
                    'slug' => $taxonomy->slug,
                ])
                ->values(),
            'assets' => $content->mediaAssets
                ->map(fn ($asset): array => [
                    'type' => $asset->type->value,
                    'title' => $asset->title,
                    'url' => rescue(fn (): ?string => $asset->publicUrl(), null, report: false),
                    'alt_text' => $asset->alt_text,
                ])
                ->filter(fn (array $asset): bool => $asset['url'] !== null)
                ->values(),
        ]);
    }

    private function isLiveContent(EditorialContent $content): bool
    {
        if (in_array($content->status, [EditorialStatus::Draft, EditorialStatus::Archived], true)) {
            return false;
        }

        if ($content->scheduled_at !== null && $content->scheduled_at->isFuture()) {
            return false;
        }

        return $content->status !== EditorialStatus::Scheduled || $content->scheduled_at !== null;
    }
}
