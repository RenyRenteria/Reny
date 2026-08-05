<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Services\Photos\PhotoLibraryService;
use App\Services\PublicCmsContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PhotoLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = Photo::query()->with('album');

        if ($filters['album'] === 'none') {
            $query->whereNull('album_id');
        } elseif ($filters['album'] !== '') {
            $query->where('album_id', (int) $filters['album']);
        }

        if ($filters['visibility'] !== '') {
            $query->where('visibility', $filters['visibility']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['date'] !== '') {
            $query->whereDate('created_at', $filters['date']);
        }

        return view('admin.photos.index', [
            'albums' => PhotoAlbum::query()
                ->withCount('photos')
                ->with(['coverPhoto', 'photos:id,album_id,caption,metadata'])
                ->orderBy('order_index')
                ->orderBy('id')
                ->get(),
            'filters' => $filters,
            'photos' => $query->ordered()->get(),
            'statuses' => PhotoStatus::cases(),
            'visibilities' => PhotoVisibility::cases(),
            'limits' => [
                'max_file_kb' => (int) config('photos.max_file_kb', 20 * 1024),
                'max_batch_files' => (int) config('photos.max_batch_files', 100),
                'large_batch_threshold' => (int) config('photos.large_batch_threshold', 15),
            ],
        ]);
    }

    public function upload(Request $request, PhotoLibraryService $photos): JsonResponse|RedirectResponse
    {
        [$attributes, $files] = $this->validatedUploadPayload($request);
        $result = $photos->storeUploads($request->user(), $attributes, $files);
        $message = $result['queued']
            ? 'Upload recibido. Las fotos estan en processing mientras corre la cola.'
            : $result['photos']->count().' foto'.($result['photos']->count() === 1 ? '' : 's').' cargada'.($result['photos']->count() === 1 ? '' : 's').'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'queued' => $result['queued'],
                'album_id' => $result['album']?->id,
                'photos' => $result['photos']->map(fn (Photo $photo): array => $this->photoPayload($photo))->values(),
                'redirect_url' => route('admin.photos.index'),
            ], 201);
        }

        return redirect()->route('admin.photos.index')->with('status', $message);
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        PhotoAlbum::create([
            ...$validated,
            'order_index' => (int) ($validated['order_index'] ?? PhotoAlbum::query()->max('order_index') + 1),
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
            'metadata' => ['source' => 'cms'],
        ]);
        PublicCmsContentService::bumpCacheVersion();

        return back()->with('status', 'Album creado. Ahora puedes asignarle fotos y portada.');
    }

    public function updateAlbum(Request $request, PhotoAlbum $album): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'order_index' => ['required', 'integer', 'min:0'],
            'cover_photo_id' => ['nullable', 'integer', Rule::exists('photos', 'id')->where('album_id', $album->id)],
        ]);

        $album->update([
            ...$validated,
            'cover_photo_id' => $validated['cover_photo_id'] ?? null,
            'updated_by_id' => $request->user()?->id,
        ]);
        PublicCmsContentService::bumpCacheVersion();

        return back()->with('status', 'Album actualizado.');
    }

    public function destroyAlbum(Request $request, PhotoAlbum $album): RedirectResponse
    {
        $validated = $request->validate([
            'reassign_album_id' => [
                'nullable',
                'integer',
                Rule::exists('photo_albums', 'id'),
                Rule::notIn([$album->id]),
            ],
        ]);
        $photoCount = $album->photos()->count();
        $reassignAlbumId = isset($validated['reassign_album_id']) ? (int) $validated['reassign_album_id'] : null;

        if ($photoCount > 0 && $reassignAlbumId === null) {
            return back()->withErrors([
                'reassign_album_id' => 'Reassign the album photos before deleting it.',
            ]);
        }

        DB::transaction(function () use ($album, $reassignAlbumId, $request): void {
            if ($reassignAlbumId !== null) {
                $album->photos()->update(['album_id' => $reassignAlbumId]);
                $target = PhotoAlbum::query()->findOrFail($reassignAlbumId);
                $target->cover_photo_id ??= $target->photos()->value('id');
                $target->updated_by_id = $request->user()?->id;
                $target->save();
            }

            $album->delete();
        });
        PublicCmsContentService::bumpCacheVersion();

        return back()->with('status', 'Album eliminado; las fotos y sus URLs se conservaron.');
    }

    public function update(Request $request, Photo $photo, PhotoLibraryService $photos): RedirectResponse
    {
        $validated = $request->validate([
            'album_id' => ['nullable', 'integer', Rule::exists('photo_albums', 'id')],
            'visibility' => ['required', Rule::in(PhotoVisibility::values())],
            'status' => ['required', Rule::in(PhotoStatus::values())],
            'caption' => ['nullable', 'string', 'max:500'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $photos->update($photo, [
            ...$validated,
            'album_id' => $validated['album_id'] ?? null,
        ]);

        return back()->with('status', 'Foto actualizada.');
    }

    public function destroy(Photo $photo, PhotoLibraryService $photos): RedirectResponse
    {
        $photos->delete($photo);

        return back()->with('status', 'Foto eliminada y archivos borrados.');
    }

    public function batch(Request $request, PhotoLibraryService $photos): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete', 'mark_public', 'mark_member_only'])],
            'photo_ids' => ['nullable', 'array'],
            'photo_ids.*' => ['integer', Rule::exists('photos', 'id')],
            'album_id' => ['nullable', 'integer', Rule::exists('photo_albums', 'id')],
        ]);

        $query = Photo::query();

        if (! empty($validated['album_id'])) {
            $query->where('album_id', (int) $validated['album_id']);
        } else {
            $query->whereIn('id', $validated['photo_ids'] ?? []);
        }

        $targets = $query->get();

        if ($validated['action'] === 'delete') {
            $targets->each(fn (Photo $photo) => $photos->delete($photo));

            return back()->with('status', $targets->count().' foto'.($targets->count() === 1 ? '' : 's').' eliminada'.($targets->count() === 1 ? '' : 's').'.');
        }

        $photos->setVisibility(
            $targets,
            $validated['action'] === 'mark_public' ? PhotoVisibility::Public : PhotoVisibility::MemberOnly,
        );

        return back()->with('status', 'Visibilidad actualizada para '.$targets->count().' foto'.($targets->count() === 1 ? '' : 's').'.');
    }

    public function reorder(Request $request, PhotoLibraryService $photos): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'min:0'],
        ]);

        $photos->reorder($validated['order']);

        return back()->with('status', 'Orden de fotos actualizado.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, UploadedFile>}
     */
    private function validatedUploadPayload(Request $request): array
    {
        $files = $this->normalizedFiles($request);
        $data = [
            ...$request->except(['file', 'files', 'photos']),
            'files' => $files,
        ];

        $validator = Validator::make($data, [
            'album_title' => ['nullable', 'string', 'max:160'],
            'album_description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => ['nullable', Rule::in(PhotoVisibility::values())],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:500'],
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('photos.max_batch_files', 100)],
            'files.*' => [
                'required',
                'file',
                'max:'.(int) config('photos.max_file_kb', 20 * 1024),
                'mimes:'.implode(',', config('photos.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp'])),
                'mimetypes:'.implode(',', config('photos.allowed_mime_types', ['image/jpeg', 'image/png', 'image/webp'])),
            ],
        ]);

        $validated = $validator->validate();

        return [
            [
                'album_title' => $validated['album_title'] ?? null,
                'album_description' => $validated['album_description'] ?? null,
                'visibility' => $validated['visibility'] ?? [],
                'captions' => $validated['captions'] ?? [],
            ],
            $files,
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function normalizedFiles(Request $request): array
    {
        $files = $request->file('files') ?: $request->file('photos');

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
     * @return array{album: string, visibility: string, status: string, date: string}
     */
    private function filters(Request $request): array
    {
        $album = (string) $request->query('album', '');
        $visibility = (string) $request->query('visibility', '');
        $status = (string) $request->query('status', '');
        $date = (string) $request->query('date', '');

        return [
            'album' => $album === 'none' || ctype_digit($album) ? $album : '',
            'visibility' => in_array($visibility, PhotoVisibility::values(), true) ? $visibility : '',
            'status' => in_array($status, PhotoStatus::values(), true) ? $status : '',
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoPayload(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'album_id' => $photo->album_id,
            'visibility' => $photo->visibility->value,
            'status' => $photo->status->value,
            'caption' => $photo->caption,
        ];
    }
}
