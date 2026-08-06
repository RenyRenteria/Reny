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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityPostController extends Controller
{
    private const MAX_IMAGE_ATTACHMENT_BYTES = 512 * 1024 * 1024;

    public function store(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
    ): JsonResponse|RedirectResponse {
        return $this->persist($request, $workflow, $library);
    }

    public function update(
        Request $request,
        EditorialContent $post,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library,
    ): JsonResponse|RedirectResponse {
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
    ): JsonResponse|RedirectResponse {
        $isNewPost = $post === null;
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
            'attachments' => ['nullable', 'array', 'max:12'],
            'attachments.*' => [
                'file',
                'extensions:avif,gif,jpeg,jpg,mov,mp4,png,webm,webp',
                'mimes:avif,gif,jpeg,jpg,mov,mp4,png,webm,webp',
            ],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer'],
        ], [
            'scheduled_at.required_if' => 'Selecciona la fecha y hora para programar el post.',
            'scheduled_at.after' => 'La fecha programada debe estar en el futuro.',
            'cover_image.uploaded' => 'The cover image could not be uploaded. Check the server upload limit and try again.',
            'attachments.max' => 'Puedes adjuntar hasta 12 fotos o videos por post.',
            'attachments.*.uploaded' => 'The file could not be uploaded. Check the server upload limit and try again.',
            'attachments.*.extensions' => 'Los adjuntos deben ser fotos (AVIF, GIF, JPG, PNG, WEBP) o videos (MOV, MP4, WEBM).',
            'attachments.*.mimes' => 'Los adjuntos deben ser fotos (AVIF, GIF, JPG, PNG, WEBP) o videos (MOV, MP4, WEBM).',
        ]);

        $body = CommunityPostContent::sanitize((string) $validated['body']);

        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages([
                'body' => 'Escribe el contenido del post.',
            ]);
        }

        $post?->loadMissing('mediaAssets');
        $removeAttachmentIds = collect($validated['remove_attachment_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique();
        $existingAttachments = $this->existingAttachments($post, $removeAttachmentIds);
        $attachmentFiles = collect(Arr::wrap($request->file('attachments')))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();

        $this->validateAttachmentSizes($attachmentFiles);

        if ($existingAttachments->count() + $attachmentFiles->count() > 12) {
            throw ValidationException::withMessages([
                'attachments' => 'Puedes mantener hasta 12 fotos o videos adjuntos por post.',
            ]);
        }

        $newAttachments = collect();

        try {
            $newAttachments = $this->storeAttachments($request, $library, $attachmentFiles);
            $cover = $this->coverAsset($request, $library, $post);
        } catch (MediaUploadException $exception) {
            $newAttachments->each(fn (MediaAsset $asset) => $library->delete($asset));

            if ($request->expectsJson()) {
                $field = $request->hasFile('attachments') ? 'attachments' : 'cover_image';

                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => [$field => [$exception->getMessage()]],
                ], 503);
            }

            return back()->withErrors([
                ($request->hasFile('attachments') ? 'attachments' : 'cover_image') => $exception->getMessage(),
            ])->withInput();
        }

        $attachments = $existingAttachments->concat($newAttachments)->values();

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
            'media_assets' => [
                ...($cover ? [[
                    'id' => $cover->id,
                    'role' => 'cover',
                    'sort_order' => 0,
                ]] : []),
                ...$attachments
                    ->map(fn (MediaAsset $asset, int $index): array => [
                        'id' => $asset->id,
                        'role' => 'attachment',
                        'sort_order' => $index + 1,
                    ])
                    ->all(),
            ],
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('admin.site-editor.show', ['page' => 'community']),
                'post' => [
                    'id' => $saved->id,
                    'status' => $saved->status->value,
                ],
            ], $isNewPost ? 201 : 200);
        }

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
            ?? $post->mediaAssets->first(
                fn (MediaAsset $asset): bool => $asset->pivot?->role === 'cover'
            );
    }

    /**
     * @param  Collection<int, int>  $removeAttachmentIds
     * @return Collection<int, MediaAsset>
     */
    private function existingAttachments(
        ?EditorialContent $post,
        Collection $removeAttachmentIds,
    ): Collection {
        if (! $post) {
            return collect();
        }

        return $post->mediaAssets
            ->filter(fn (MediaAsset $asset): bool => $asset->pivot?->role === 'attachment')
            ->reject(fn (MediaAsset $asset): bool => $removeAttachmentIds->contains($asset->id))
            ->values();
    }

    /**
     * @param  Collection<int, UploadedFile>  $files
     * @return Collection<int, MediaAsset>
     */
    private function storeAttachments(
        Request $request,
        MediaLibraryService $library,
        Collection $files,
    ): Collection {
        $assets = collect();

        try {
            foreach ($files->groupBy(
                fn (UploadedFile $file): string => $this->attachmentType($file)->value
            ) as $type => $typedFiles) {
                $assets = $assets->concat($library->storeUploads($request->user(), [
                    'type' => $type,
                    'title' => trim((string) $request->input('title')).' attachment',
                    'is_public' => true,
                    'alt_text' => $type === MediaAssetType::Image->value
                        ? trim((string) $request->input('title'))
                        : null,
                    'metadata' => ['source' => 'community_post_attachment'],
                ], $typedFiles->all()));
            }
        } catch (MediaUploadException $exception) {
            $assets->each(fn (MediaAsset $asset) => $library->delete($asset));

            throw $exception;
        }

        return $assets->values();
    }

    /**
     * @param  Collection<int, UploadedFile>  $files
     */
    private function validateAttachmentSizes(Collection $files): void
    {
        $videoMaxBytes = (int) config('media.types.'.MediaAssetType::Video->value.'.max_bytes');

        foreach ($files as $index => $file) {
            $isVideo = $this->attachmentType($file) === MediaAssetType::Video;
            $maxBytes = $isVideo ? $videoMaxBytes : self::MAX_IMAGE_ATTACHMENT_BYTES;

            if (($file->getSize() ?: 0) <= $maxBytes) {
                continue;
            }

            throw ValidationException::withMessages([
                "attachments.{$index}" => $isVideo
                    ? 'Cada video puede pesar hasta 1 GB.'
                    : 'Cada foto puede pesar hasta 512 MB.',
            ]);
        }
    }

    private function attachmentType(UploadedFile $file): MediaAssetType
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return str_starts_with((string) $file->getMimeType(), 'video/')
            || in_array($extension, ['mov', 'mp4', 'webm'], true)
            ? MediaAssetType::Video
            : MediaAssetType::Image;
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
