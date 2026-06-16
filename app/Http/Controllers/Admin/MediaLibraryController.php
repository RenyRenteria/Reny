<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaAssetType;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Services\Media\MuxVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaLibraryController extends Controller
{
    public function index(): View
    {
        return view('admin.media', [
            'assets' => MediaAsset::query()
                ->latest()
                ->limit(24)
                ->get(),
            'types' => MediaAssetType::cases(),
            'limits' => config('media.types'),
        ]);
    }

    public function store(Request $request, MediaLibraryService $library): JsonResponse|RedirectResponse
    {
        [$attributes, $files] = $this->validatedUploadPayload($request);

        try {
            $assets = $library->storeUploads($request->user(), $attributes, $files);
        } catch (MediaUploadException $exception) {
            return $this->uploadError($request, $exception->getMessage());
        }

        $message = sprintf('%d media asset%s uploaded.', $assets->count(), $assets->count() === 1 ? '' : 's');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'assets' => $assets->map(fn (MediaAsset $asset): array => $this->assetPayload($asset))->values(),
            ], 201);
        }

        return redirect()->route('admin.media.index')->with('status', $message);
    }

    public function createMuxDirectUpload(
        Request $request,
        MediaLibraryService $library,
        MuxVideoService $mux
    ): JsonResponse|RedirectResponse {
        $attributes = $this->validatedMuxPayload($request);

        try {
            $result = $library->createMuxDirectUpload($request->user(), $attributes, $mux);
        } catch (MediaUploadException $exception) {
            return $this->uploadError($request, $exception->getMessage());
        }

        /** @var MediaAsset $asset */
        $asset = $result['asset'];
        $message = 'Mux direct upload created.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'upload_url' => $result['upload_url'],
                'asset' => $this->assetPayload($asset),
            ], 201);
        }

        return redirect()
            ->route('admin.media.index')
            ->with('status', $message.' Upload ID: '.$asset->mux_upload_id);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, UploadedFile>}
     */
    private function validatedUploadPayload(Request $request): array
    {
        $files = $this->normalizedFiles($request);
        $data = [
            ...$request->except(['file', 'files']),
            'files' => $files,
        ];

        $validator = Validator::make($data, [
            'type' => ['required', Rule::in(MediaAssetType::values())],
            'title' => ['nullable', 'string', 'max:160'],
            'alt_text' => ['nullable', 'string', 'max:180'],
            'is_public' => ['nullable', 'boolean'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file'],
        ]);

        $validator->after(function ($validator) use ($data, $files): void {
            $this->validateMediaConstraints($validator, (string) ($data['type'] ?? ''), $files, $data);
        });

        $validated = $validator->validate();
        $type = MediaAssetType::from($validated['type']);

        return [
            [
                'type' => $type->value,
                'title' => $validated['title'] ?? null,
                'alt_text' => $validated['alt_text'] ?? null,
                'is_public' => $this->booleanValue($validated['is_public'] ?? true),
                'duration_seconds' => isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
                'metadata' => $validated['metadata'] ?? [],
            ],
            $files,
        ];
    }

    private function validatedMuxPayload(Request $request): array
    {
        $maxBytes = (int) config('media.types.'.MediaAssetType::ShortVideo->value.'.max_bytes');
        $maxDuration = (int) config('media.short_video_duration_seconds');

        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:160'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:160'],
            'size_bytes' => ['nullable', 'integer', 'min:1', 'max:'.$maxBytes],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:'.$maxDuration],
            'is_public' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $limits = config('media.types.'.MediaAssetType::ShortVideo->value, []);
            $extension = strtolower(pathinfo((string) $request->input('original_filename'), PATHINFO_EXTENSION));

            if (! in_array($extension, $limits['extensions'] ?? [], true)) {
                $validator->errors()->add('original_filename', 'File extension is not allowed for short video.');
            }

            $mime = $request->input('mime_type');

            if ($mime !== null && ! in_array($mime, $limits['mime_types'] ?? [], true)) {
                $validator->errors()->add('mime_type', 'File MIME type is not allowed for short video.');
            }
        });

        $validated = $validator->validate();

        return [
            'title' => $validated['title'] ?? null,
            'original_filename' => $validated['original_filename'],
            'mime_type' => $validated['mime_type'],
            'size_bytes' => isset($validated['size_bytes']) ? (int) $validated['size_bytes'] : 0,
            'duration_seconds' => (int) $validated['duration_seconds'],
            'is_public' => $this->booleanValue($validated['is_public'] ?? true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function normalizedFiles(Request $request): array
    {
        $files = $request->file('files');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files, fn (mixed $file): bool => $file instanceof UploadedFile));
        }

        $file = $request->file('file');

        return $file instanceof UploadedFile ? [$file] : [];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $data
     */
    private function validateMediaConstraints($validator, string $typeValue, array $files, array $data): void
    {
        $type = MediaAssetType::tryFrom($typeValue);

        if (! $type) {
            return;
        }

        $limits = config("media.types.{$type->value}", []);
        $batchBytes = 0;
        $isPublic = $this->booleanValue($data['is_public'] ?? true);

        if ($type->requiresAltTextWhenPublic() && $isPublic && blank($data['alt_text'] ?? null)) {
            $validator->errors()->add('alt_text', 'Alt text is required for public images.');
        }

        if ($type === MediaAssetType::ShortVideo && blank($data['duration_seconds'] ?? null)) {
            $validator->errors()->add('duration_seconds', 'Duration is required for short video uploads.');
        }

        foreach ($files as $index => $file) {
            $batchBytes += $file->getSize() ?: 0;

            if (($file->getSize() ?: 0) > (int) ($limits['max_bytes'] ?? 0)) {
                $validator->errors()->add("files.{$index}", 'File exceeds the approved V1 size limit.');
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

            if (! in_array($extension, $limits['extensions'] ?? [], true)) {
                $validator->errors()->add("files.{$index}", 'File extension is not allowed for this media type.');
            }

            $mime = $file->getMimeType() ?: $file->getClientMimeType();

            if (! in_array($mime, $limits['mime_types'] ?? [], true)) {
                $validator->errors()->add("files.{$index}", 'File MIME type is not allowed for this media type.');
            }
        }

        if ($batchBytes > (int) config('media.batch_limit_bytes')) {
            $validator->errors()->add('files', 'Batch upload exceeds the approved V1 size limit.');
        }
    }

    private function uploadError(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 503);
        }

        return back()->withErrors(['media' => $message])->withInput();
    }

    private function assetPayload(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'type' => $asset->type->value,
            'title' => $asset->title,
            'original_filename' => $asset->original_filename,
            'size_bytes' => $asset->size_bytes,
            'is_public' => $asset->is_public,
            'alt_text' => $asset->alt_text,
            'processing_status' => $asset->processing_status->value,
            'url' => $asset->publicUrl(),
            'mux' => [
                'upload_id' => $asset->mux_upload_id,
                'asset_id' => $asset->mux_asset_id,
                'playback_id' => $asset->mux_playback_id,
                'status' => $asset->mux_status,
            ],
        ];
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
