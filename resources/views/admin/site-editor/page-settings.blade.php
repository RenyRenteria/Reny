@php
    $field = fn (string $key): string => (string) old($key, $pageSettings[$key] ?? '');
    $coverAssetId = (string) old('cover_asset_id', $pageSettings['cover_asset_id'] ?? '');
@endphp

<section class="admin-panel page-settings-editor" aria-labelledby="page-settings-title">
    <div class="admin-section-head">
        <div>
            <p class="admin-kicker">Page settings</p>
            <h2 id="page-settings-title">{{ $pageConfig['label'] }} header and SEO</h2>
        </div>
        <span class="admin-status-pill {{ ($pageSettings['_editor_status'] ?? 'draft') === 'published' ? 'admin-status-success' : 'admin-status-warning' }}">
            {{ $pageSettings['_editor_status'] ?? 'draft' }}
        </span>
    </div>

    <form class="admin-form-grid admin-form-grid-wide" method="POST" action="{{ route('admin.site-editor.page-settings.update', ['page' => $activePage]) }}" enctype="multipart/form-data">
        @csrf
        <label><span>Eyebrow</span><input name="eyebrow" value="{{ $field('eyebrow') }}" maxlength="80"></label>
        <label><span>Title</span><input name="title" value="{{ $field('title') }}" maxlength="120" required></label>
        <label><span>Subtitle</span><input name="subtitle" value="{{ $field('subtitle') }}" maxlength="160"></label>
        <label class="admin-field-wide"><span>Description</span><textarea name="description" maxlength="500">{{ $field('description') }}</textarea></label>
        <label>
            <span>Existing cover</span>
            @include('admin.partials.asset-select', [
                'name' => 'cover_asset_id',
                'selected' => $coverAssetId,
                'mediaAssets' => $pageSettingsForm['mediaAssets'],
                'types' => ['image', 'thumbnail'],
            ])
        </label>
        <label><span>Upload cover</span><input name="cover" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label>
        <label class="admin-field-wide"><span>Cover alt text</span><input name="cover_alt" value="{{ $field('cover_alt') }}" maxlength="180"></label>

        <label><span>Meta title</span><input name="meta_title" value="{{ $field('meta_title') }}" maxlength="160"></label>
        <label><span>Canonical URL</span><input name="canonical_url" type="url" value="{{ $field('canonical_url') }}"></label>
        <label class="admin-field-wide"><span>Meta description</span><textarea name="meta_description" maxlength="320">{{ $field('meta_description') }}</textarea></label>
        <label><span>Open Graph title</span><input name="og_title" value="{{ $field('og_title') }}" maxlength="160"></label>
        <label><span>Open Graph image URL</span><input name="og_image" type="url" value="{{ $field('og_image') }}"></label>
        <label class="admin-field-wide"><span>Open Graph description</span><textarea name="og_description" maxlength="320">{{ $field('og_description') }}</textarea></label>
        <label><span>Twitter card</span><select name="twitter_card"><option value="summary_large_image" @selected($field('twitter_card') === 'summary_large_image')>summary_large_image</option><option value="summary" @selected($field('twitter_card') === 'summary')>summary</option></select></label>
        <label><span>Twitter title</span><input name="twitter_title" value="{{ $field('twitter_title') }}" maxlength="160"></label>
        <label class="admin-field-wide"><span>Twitter description</span><textarea name="twitter_description" maxlength="320">{{ $field('twitter_description') }}</textarea></label>
        <label class="admin-field-wide"><span>Twitter image URL</span><input name="twitter_image" type="url" value="{{ $field('twitter_image') }}"></label>

        <div class="admin-form-actions admin-field-wide">
            <button class="admin-button admin-button-primary" name="action" value="publish" type="submit" @disabled(! auth()->user()->canPublishContent())>Publish settings</button>
            <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Save draft</button>
        </div>
    </form>
</section>
