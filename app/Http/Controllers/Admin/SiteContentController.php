<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Single;
use App\Models\SiteHero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'hero' => SiteHero::query()->first() ?? SiteHero::fallback(),
            'albums' => Album::query()->orderBy('sort_order')->orderBy('id')->get(),
            'singles' => Single::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function updateHero(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'link_text' => ['nullable', 'string', 'max:140'],
            'badge_text' => ['nullable', 'string', 'max:80'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $hero = SiteHero::query()->firstOrNew();
        $hero->fill(collect($validated)->except(['hero_image', 'remove_image'])->all());

        if ($request->boolean('remove_image')) {
            $this->deleteUpload($hero->image_path);
            $hero->image_path = null;
        }

        if ($request->hasFile('hero_image')) {
            $hero->image_path = $this->replaceUpload($request, 'hero_image', $hero->image_path);
        }

        $hero->save();

        return back()->with('status', 'Hero content updated.');
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        $validated = $this->validateAlbum($request);
        $album = new Album($this->albumPayload($request, $validated));

        if ($request->hasFile('image')) {
            $album->image_path = $request->file('image')->store('site-content/albums', 'public');
        }

        $album->save();

        return back()->with('status', 'Album added.');
    }

    public function updateAlbum(Request $request, Album $album): RedirectResponse
    {
        $validated = $this->validateAlbum($request);
        $album->fill($this->albumPayload($request, $validated));

        if ($request->boolean('remove_image')) {
            $this->deleteUpload($album->image_path);
            $album->image_path = null;
        }

        if ($request->hasFile('image')) {
            $album->image_path = $this->replaceUpload($request, 'image', $album->image_path, 'site-content/albums');
        }

        $album->save();

        return back()->with('status', 'Album updated.');
    }

    public function destroyAlbum(Album $album): RedirectResponse
    {
        $this->deleteUpload($album->image_path);
        $album->delete();

        return back()->with('status', 'Album removed.');
    }

    public function storeSingle(Request $request): RedirectResponse
    {
        $validated = $this->validateSingle($request);
        $single = new Single($this->singlePayload($request, $validated));

        if ($request->hasFile('image')) {
            $single->image_path = $request->file('image')->store('site-content/singles', 'public');
        }

        if ($request->hasFile('audio_file')) {
            $single->audio_path = $request->file('audio_file')->store('site-content/audio', 'public');
        }

        $single->save();

        return back()->with('status', 'Single added.');
    }

    public function updateSingle(Request $request, Single $single): RedirectResponse
    {
        $validated = $this->validateSingle($request);
        $single->fill($this->singlePayload($request, $validated));

        if ($request->boolean('remove_image')) {
            $this->deleteUpload($single->image_path);
            $single->image_path = null;
        }

        if ($request->boolean('remove_audio')) {
            $this->deleteUpload($single->audio_path);
            $single->audio_path = null;
        }

        if ($request->hasFile('image')) {
            $single->image_path = $this->replaceUpload($request, 'image', $single->image_path, 'site-content/singles');
        }

        if ($request->hasFile('audio_file')) {
            $single->audio_path = $this->replaceUpload($request, 'audio_file', $single->audio_path, 'site-content/audio');
        }

        $single->save();

        return back()->with('status', 'Single updated.');
    }

    public function destroySingle(Single $single): RedirectResponse
    {
        $this->deleteUpload($single->image_path);
        $this->deleteUpload($single->audio_path);
        $single->delete();

        return back()->with('status', 'Single removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAlbum(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'track_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'cover_label' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function albumPayload(Request $request, array $validated): array
    {
        return [
            'title' => $validated['title'],
            'track_count' => $validated['track_count'] ?? 0,
            'cover_label' => $validated['cover_label'] ?: $validated['title'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_published' => $request->boolean('is_published'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSingle(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'artist' => ['nullable', 'string', 'max:120'],
            'audio_url' => ['nullable', 'url', 'max:500'],
            'audio_file' => ['nullable', 'file', 'mimes:mp3,wav,m4a,aac,ogg', 'max:51200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'remove_audio' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function singlePayload(Request $request, array $validated): array
    {
        return [
            'title' => $validated['title'],
            'artist' => $validated['artist'] ?? null,
            'audio_url' => $validated['audio_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_published' => $request->boolean('is_published'),
        ];
    }

    private function replaceUpload(Request $request, string $field, ?string $existingPath, string $directory = 'site-content/hero'): string
    {
        $this->deleteUpload($existingPath);

        return $request->file($field)->store($directory, 'public');
    }

    private function deleteUpload(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
