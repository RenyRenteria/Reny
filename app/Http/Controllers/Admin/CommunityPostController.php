<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\CommunityPostReaction;
use App\Models\CommunityPostReply;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Services\EditorialWorkflowService;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Support\CommunityPostContent;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityPostController extends Controller
{
    public function store(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
    ): RedirectResponse {
        return $this->persist($request, $workflow, $library);
    }

    public function update(
        Request $request,
        EditorialContent $post,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
    ): RedirectResponse {
        $this->assertPost($post);

        return $this->persist($request, $workflow, $library, $post);
    }

    public function destroy(EditorialContent $post): RedirectResponse
    {
        $this->assertPost($post);
        $postKey = 'cms-post-'.$post->id;
        $title = $post->title;

        DB::transaction(function () use ($post, $postKey): void {
            CommunityPostReaction::query()->where('post_key', $postKey)->delete();
            CommunityPostReply::query()->where('post_key', $postKey)->delete();
            $post->delete();
        });

        return $this->backToCommunity(sprintf('Post "%s" eliminado.', $title));
    }

    public function moderateReply(Request $request, CommunityPostReply $reply): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['hide', 'delete'])],
        ]);

        if ($validated['action'] === 'delete') {
            $reply->delete();
            $message = 'Comentario eliminado permanentemente.';
        } else {
            $reply->update(['status' => 'removed']);
            $message = 'Comentario ocultado del feed.';
        }

        return $this->backToCommunity($message);
    }

    private function persist(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
        ?EditorialContent $post = null,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['draft', 'publish', 'schedule'])],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:100000'],
            'published_on' => ['required', 'date'],
            'scheduled_at' => ['nullable', 'date', 'required_if:action,schedule', 'after:now'],
            'comments_enabled' => ['nullable', 'boolean'],
            'media_urls' => ['nullable', 'string', 'max:12000'],
            'cover_image' => ['nullable', 'image', 'mimes:avif,jpeg,jpg,png,webp', 'max:12288'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'scheduled_at.required_if' => 'Selecciona la fecha y hora para programar el post.',
            'scheduled_at.after' => 'La fecha programada debe estar en el futuro.',
        ]);

        $body = CommunityPostContent::sanitize((string) $validated['body']);

        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages([
                'body' => 'Escribe el contenido del post.',
            ]);
        }

        $post?->loadMissing('mediaAssets');

        try {
            $cover = $this->coverAsset($request, $library, $post);
        } catch (MediaUploadException $exception) {
            return back()->withErrors(['cover_image' => $exception->getMessage()])->withInput();
        }

        $existingMetadata = $post?->metadata ?? [];
        $metadata = [
            ...$existingMetadata,
            'published_on' => CarbonImmutable::parse(
                (string) $validated['published_on'],
                config('admin.publishing_timezone', 'America/Panama')
            )->toDateString(),
            'comments_enabled' => $request->boolean('comments_enabled'),
            'media_items' => CommunityPostContent::normalizeMediaUrls([(string) ($validated['media_urls'] ?? '')]),
        ];

        if ($cover) {
            $metadata['image_asset_id'] = $cover->id;
        } else {
            unset($metadata['image_asset_id']);
        }

        $payload = [
            'type' => ContentType::Post->value,
            'title' => trim((string) $validated['title']),
            'body' => $body,
            'visibility' => VisibilityAudience::Open->value,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'metadata' => $metadata,
            'media_assets' => $cover ? [[
                'id' => $cover->id,
                'role' => 'cover',
                'sort_order' => 0,
            ]] : [],
        ];

        $action = (string) $validated['action'];
        $saved = match ($action) {
            'publish' => $post
                ? $workflow->publish($request->user(), $post, $payload)
                : $workflow->publishNew($request->user(), $payload),
            'schedule' => $post
                ? $workflow->schedule(
                    $request->user(),
                    $post,
                    (string) $validated['scheduled_at'],
                    [],
                    $payload,
                )
                : $workflow->scheduleNew($request->user(), $payload, (string) $validated['scheduled_at']),
            default => $post
                ? $workflow->updateDraft($request->user(), $post, $payload)
                : $workflow->createDraft($request->user(), $payload),
        };

        $message = match ($action) {
            'publish' => sprintf('Post "%s" publicado.', $saved->title),
            'schedule' => sprintf('Post "%s" programado.', $saved->title),
            default => sprintf('Borrador "%s" guardado.', $saved->title),
        };

        return $this->backToCommunity($message);
    }

    private function coverAsset(
        Request $request,
        MediaLibraryService $library,
        ?EditorialContent $post,
    ): ?MediaAsset {
        if ($request->hasFile('cover_image')) {
            return $library->storeUploads($request->user(), [
                'type' => MediaAssetType::Image->value,
                'title' => trim((string) $request->input('title')).' cover',
                'is_public' => true,
                'alt_text' => trim((string) $request->input('title')),
                'metadata' => ['source' => 'community_post_cover'],
            ], [$request->file('cover_image')])->first();
        }

        if ($request->boolean('remove_cover') || ! $post) {
            return null;
        }

        $coverId = (int) Arr::get($post->metadata ?? [], 'image_asset_id');

        return $post->mediaAssets->firstWhere('id', $coverId)
            ?? $post->mediaAssets->first();
    }

    private function assertPost(EditorialContent $post): void
    {
        abort_unless($post->type === ContentType::Post, 404);
    }

    private function backToCommunity(string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.site-editor.show', ['page' => 'community'])
            ->with('status', $message);
    }
}
