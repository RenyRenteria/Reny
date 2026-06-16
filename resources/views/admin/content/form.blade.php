@php
    $isEditing = $content?->exists ?? false;
    $metadata = old('metadata', $content?->metadata ?? []);
    $metaValue = fn (string $key, mixed $default = '') => old("metadata.{$key}", data_get($metadata, $key, $default));
    $selectedType = old('type', $selectedType);
    $selectedMediaIds = collect(old('media_asset_ids', $content?->mediaAssets?->pluck('id')->all() ?? []))
        ->map(fn ($id): string => (string) $id)
        ->all();
    $selectedTaxonomyIds = collect(old('taxonomy_ids', $content?->taxonomies?->pluck('id')->all() ?? []))
        ->map(fn ($id): string => (string) $id)
        ->all();
    $scheduledValue = old('scheduled_at', $content?->scheduled_at?->copy()->timezone($timezone)->format('Y-m-d\TH:i'));
    $existingWindows = $content?->releaseWindows?->map(fn ($window): array => [
        'audience' => $window->audience->value,
        'starts_at' => $window->starts_at?->copy()->timezone($timezone)->format('Y-m-d\TH:i'),
        'ends_at' => $window->ends_at?->copy()->timezone($timezone)->format('Y-m-d\TH:i'),
        'country_codes' => $window->country_codes ?? [],
    ])->all() ?? [];
    $releaseRows = collect(old('release_windows', $existingWindows))->values();
    while ($releaseRows->count() < 2) {
        $releaseRows->push(['audience' => '', 'starts_at' => '', 'ends_at' => '', 'country_codes' => []]);
    }
    $pollOptions = collect($metaValue('options', ['', '', '', '']))->values();
    while ($pollOptions->count() < 4) {
        $pollOptions->push('');
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $isEditing ? 'Edit Content' : 'Create Content' }} | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="admin-shell">
            <aside class="admin-sidebar" aria-label="Admin navigation">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <nav class="admin-nav" aria-label="CMS sections">
                    <a href="{{ route('admin.dashboard') }}">Overview</a>
                    <a href="{{ route('admin.media.index') }}">Media</a>
                    <a class="is-active" href="{{ route('admin.content.index') }}">Content</a>
                    <a href="{{ route('admin.content.index', ['status' => 'scheduled']) }}">Schedule</a>
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="admin-button admin-button-secondary" type="submit">Log out</button>
                </form>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div>
                        <p class="admin-kicker">Project 3 CMS</p>
                        <h1>{{ $isEditing ? 'Edit content' : 'Create content' }}</h1>
                    </div>

                    <div class="admin-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </div>
                </header>

                @if (session('status'))
                    <div class="auth-status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="auth-status" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form
                    class="admin-content-form"
                    method="POST"
                    action="{{ $isEditing ? route('admin.content.update', $content) : route('admin.content.store') }}"
                >
                    @csrf
                    @if ($isEditing)
                        @method('PUT')
                    @endif

                    <section class="admin-panel" aria-labelledby="content-basics-title">
                        <div class="admin-section-head">
                            <div>
                                <p class="admin-kicker">Editorial</p>
                                <h2 id="content-basics-title">Basics</h2>
                            </div>

                            @if ($isEditing)
                                <a class="admin-button admin-button-ghost" href="{{ route('admin.content.preview', $content) }}">Preview</a>
                            @endif
                        </div>

                        <div class="admin-form-grid admin-form-grid-wide">
                            <label>
                                <span>Type</span>
                                <select id="content-type" name="type" required>
                                    @foreach ($contentTypes as $type)
                                        <option value="{{ $type->value }}" @selected($selectedType === $type->value)>
                                            {{ str_replace('_', ' ', $type->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>Title</span>
                                <input name="title" type="text" maxlength="160" value="{{ old('title', $content?->title) }}" required>
                            </label>

                            <label>
                                <span>Slug</span>
                                <input name="slug" type="text" maxlength="180" value="{{ old('slug', $content?->slug) }}">
                            </label>

                            <label>
                                <span>Visibility</span>
                                <select name="visibility" required>
                                    @foreach ($visibilityAudiences as $audience)
                                        <option value="{{ $audience->value }}" @selected(old('visibility', $content?->visibility->value ?? 'open') === $audience->value)>
                                            {{ $audience->value }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label>
                                <span>Purchase key</span>
                                <input name="purchase_key" type="text" maxlength="120" value="{{ old('purchase_key', $content?->purchase_key) }}">
                            </label>

                            <label>
                                <span>Scheduled at</span>
                                <input name="scheduled_at" type="datetime-local" value="{{ $scheduledValue }}">
                            </label>

                            <label class="admin-field-wide">
                                <span>Summary</span>
                                <textarea name="summary" maxlength="500" rows="3">{{ old('summary', $content?->summary) }}</textarea>
                            </label>

                            <label class="admin-field-wide">
                                <span>Body</span>
                                <textarea name="body" rows="8">{{ old('body', $content?->body) }}</textarea>
                            </label>
                        </div>
                    </section>

                    <section class="admin-panel" aria-labelledby="type-fields-title">
                        <div class="admin-section-head">
                            <div>
                                <p class="admin-kicker">Type schema</p>
                                <h2 id="type-fields-title">Content fields</h2>
                            </div>
                        </div>

                        <fieldset class="admin-fieldset" data-type-fieldset="song">
                            <legend>Song</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Duration seconds</span>
                                    <input name="metadata[duration_seconds]" type="number" min="1" max="7200" value="{{ $metaValue('duration_seconds') }}">
                                </label>
                                <label>
                                    <span>Release date</span>
                                    <input name="metadata[release_date]" type="date" value="{{ $metaValue('release_date') }}">
                                </label>
                                <label>
                                    <span>Credits</span>
                                    <input name="metadata[credits]" type="text" maxlength="2000" value="{{ $metaValue('credits') }}">
                                </label>
                                <label>
                                    <span>Audio asset</span>
                                    <select name="metadata[audio_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if ($asset->type->value === 'audio')
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('audio_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Cover asset</span>
                                    <select name="metadata[cover_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if (in_array($asset->type->value, ['image', 'thumbnail'], true))
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('cover_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Preview visibility</span>
                                    <select name="metadata[preview_visibility]">
                                        @foreach ($visibilityAudiences as $audience)
                                            <option value="{{ $audience->value }}" @selected($metaValue('preview_visibility', 'open') === $audience->value)>{{ $audience->value }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Full visibility</span>
                                    <select name="metadata[full_visibility]">
                                        @foreach ($visibilityAudiences as $audience)
                                            <option value="{{ $audience->value }}" @selected($metaValue('full_visibility', 'member') === $audience->value)>{{ $audience->value }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="admin-field-wide">
                                    <span>Lyrics</span>
                                    <textarea name="metadata[lyrics]" rows="5">{{ $metaValue('lyrics') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="musical_album">
                            <legend>Musical album</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Release cycle</span>
                                    <input name="metadata[release_cycle]" type="text" maxlength="160" value="{{ $metaValue('release_cycle') }}">
                                </label>
                                <label>
                                    <span>Price cents</span>
                                    <input name="metadata[price_cents]" type="number" min="0" value="{{ $metaValue('price_cents') }}">
                                </label>
                                <label>
                                    <span>Cover asset</span>
                                    <select name="metadata[cover_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if (in_array($asset->type->value, ['image', 'thumbnail'], true))
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('cover_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                                <label class="admin-field-wide">
                                    <span>Tracklist</span>
                                    <textarea name="metadata[tracklist]" rows="6">{{ $metaValue('tracklist') }}</textarea>
                                </label>
                                <label class="admin-field-wide">
                                    <span>Narrative</span>
                                    <textarea name="metadata[narrative]" rows="6">{{ $metaValue('narrative') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="deluxe_album">
                            <legend>Deluxe album</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Release cycle</span>
                                    <input name="metadata[release_cycle]" type="text" maxlength="160" value="{{ $metaValue('release_cycle') }}">
                                </label>
                                <label>
                                    <span>Price cents</span>
                                    <input name="metadata[price_cents]" type="number" min="0" value="{{ $metaValue('price_cents') }}">
                                </label>
                                <label>
                                    <span>Cover asset</span>
                                    <select name="metadata[cover_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if (in_array($asset->type->value, ['image', 'thumbnail'], true))
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('cover_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                                <label class="admin-field-wide">
                                    <span>Tracklist</span>
                                    <textarea name="metadata[tracklist]" rows="6">{{ $metaValue('tracklist') }}</textarea>
                                </label>
                                <label class="admin-field-wide">
                                    <span>Narrative</span>
                                    <textarea name="metadata[narrative]" rows="6">{{ $metaValue('narrative') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="video">
                            <legend>Video</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>YouTube URL</span>
                                    <input name="metadata[youtube_url]" type="url" maxlength="500" value="{{ $metaValue('youtube_url') }}">
                                </label>
                                <label>
                                    <span>Category</span>
                                    <input name="metadata[category]" type="text" maxlength="120" value="{{ $metaValue('category') }}">
                                </label>
                                <label>
                                    <span>Playlist</span>
                                    <input name="metadata[playlist]" type="text" maxlength="160" value="{{ $metaValue('playlist') }}">
                                </label>
                                <label>
                                    <span>Access tier</span>
                                    <select name="metadata[access_tier]">
                                        @foreach ($visibilityAudiences as $audience)
                                            <option value="{{ $audience->value }}" @selected($metaValue('access_tier', 'open') === $audience->value)>{{ $audience->value }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Thumbnail</span>
                                    <select name="metadata[thumbnail_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if (in_array($asset->type->value, ['image', 'thumbnail'], true))
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('thumbnail_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="photo">
                            <legend>Photo</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Image asset</span>
                                    <select name="metadata[image_asset_id]">
                                        <option value="">None</option>
                                        @foreach ($mediaAssets as $asset)
                                            @if (in_array($asset->type->value, ['image', 'thumbnail'], true))
                                                <option value="{{ $asset->id }}" @selected((string) $metaValue('image_asset_id') === (string) $asset->id)>
                                                    {{ $asset->title ?: $asset->original_filename }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Location</span>
                                    <input name="metadata[location]" type="text" maxlength="160" value="{{ $metaValue('location') }}">
                                </label>
                                <label>
                                    <span>Tags</span>
                                    <input name="metadata[tags]" type="text" maxlength="500" value="{{ $metaValue('tags') }}">
                                </label>
                                <label class="admin-field-wide">
                                    <span>Caption</span>
                                    <textarea name="metadata[caption]" rows="4">{{ $metaValue('caption') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="gallery">
                            <legend>Gallery</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Image count</span>
                                    <input name="metadata[image_count]" type="number" min="1" max="200" value="{{ $metaValue('image_count') }}">
                                </label>
                                <label>
                                    <span>Location</span>
                                    <input name="metadata[location]" type="text" maxlength="160" value="{{ $metaValue('location') }}">
                                </label>
                                <label>
                                    <span>Tags</span>
                                    <input name="metadata[tags]" type="text" maxlength="500" value="{{ $metaValue('tags') }}">
                                </label>
                                <label class="admin-field-wide">
                                    <span>Caption</span>
                                    <textarea name="metadata[caption]" rows="4">{{ $metaValue('caption') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="post">
                            <legend>Post</legend>
                            <div class="admin-form-grid">
                                <label class="admin-field-wide">
                                    <span>Links</span>
                                    <textarea name="metadata[links]" rows="4">{{ $metaValue('links') }}</textarea>
                                </label>
                                <label class="admin-checkbox">
                                    <input name="metadata[comments_enabled]" type="hidden" value="0">
                                    <input name="metadata[comments_enabled]" type="checkbox" value="1" @checked((bool) $metaValue('comments_enabled', true))>
                                    <span>Comments</span>
                                </label>
                                <label class="admin-checkbox">
                                    <input name="metadata[is_pinned]" type="hidden" value="0">
                                    <input name="metadata[is_pinned]" type="checkbox" value="1" @checked((bool) $metaValue('is_pinned', false))>
                                    <span>Pinned</span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="poll">
                            <legend>Poll</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Question</span>
                                    <input name="metadata[question]" type="text" maxlength="220" value="{{ $metaValue('question') }}">
                                </label>
                                <label>
                                    <span>Closes at</span>
                                    <input name="metadata[closes_at]" type="datetime-local" value="{{ $metaValue('closes_at') }}">
                                </label>
                                <label>
                                    <span>Eligibility</span>
                                    <select name="metadata[eligibility]">
                                        @foreach ($visibilityAudiences as $audience)
                                            <option value="{{ $audience->value }}" @selected($metaValue('eligibility', 'open') === $audience->value)>{{ $audience->value }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Results</span>
                                    <select name="metadata[results_visibility]">
                                        <option value="public" @selected($metaValue('results_visibility', 'public') === 'public')>public</option>
                                        <option value="private" @selected($metaValue('results_visibility') === 'private')>private</option>
                                    </select>
                                </label>
                                @foreach ($pollOptions as $index => $option)
                                    <label>
                                        <span>Option {{ $index + 1 }}</span>
                                        <input name="metadata[options][]" type="text" maxlength="120" value="{{ $option }}">
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="product">
                            <legend>Product</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Kind</span>
                                    <select name="metadata[product_kind]">
                                        @foreach (['digital', 'physical', 'subscription', 'drop', 'bundle'] as $kind)
                                            <option value="{{ $kind }}" @selected($metaValue('product_kind') === $kind)>{{ $kind }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>SKU</span>
                                    <input name="metadata[sku]" type="text" maxlength="120" value="{{ $metaValue('sku') }}">
                                </label>
                                <label>
                                    <span>Price cents</span>
                                    <input name="metadata[price_cents]" type="number" min="0" value="{{ $metaValue('price_cents') }}">
                                </label>
                                <label>
                                    <span>Inventory</span>
                                    <input name="metadata[inventory]" type="number" min="0" value="{{ $metaValue('inventory') }}">
                                </label>
                                <label class="admin-field-wide">
                                    <span>Fulfillment</span>
                                    <textarea name="metadata[fulfillment_note]" rows="4">{{ $metaValue('fulfillment_note') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="event">
                            <legend>Event</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Kind</span>
                                    <select name="metadata[event_kind]">
                                        @foreach (['physical', 'digital', 'listening_session'] as $kind)
                                            <option value="{{ $kind }}" @selected($metaValue('event_kind') === $kind)>{{ $kind }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Starts at</span>
                                    <input name="metadata[starts_at]" type="datetime-local" value="{{ $metaValue('starts_at') }}">
                                </label>
                                <label>
                                    <span>Location</span>
                                    <input name="metadata[location]" type="text" maxlength="180" value="{{ $metaValue('location') }}">
                                </label>
                                <label>
                                    <span>Inventory</span>
                                    <input name="metadata[inventory]" type="number" min="0" value="{{ $metaValue('inventory') }}">
                                </label>
                                <label>
                                    <span>Price cents</span>
                                    <input name="metadata[price_cents]" type="number" min="0" value="{{ $metaValue('price_cents') }}">
                                </label>
                                <label>
                                    <span>Ticketing</span>
                                    <select name="metadata[ticketing_mode]">
                                        <option value="rsvp" @selected($metaValue('ticketing_mode', 'rsvp') === 'rsvp')>rsvp</option>
                                        <option value="ticket" @selected($metaValue('ticketing_mode') === 'ticket')>ticket</option>
                                    </select>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="drop">
                            <legend>Drop</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Kind</span>
                                    <select name="metadata[drop_kind]">
                                        @foreach (['product', 'content', 'bundle'] as $kind)
                                            <option value="{{ $kind }}" @selected($metaValue('drop_kind') === $kind)>{{ $kind }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Opens at</span>
                                    <input name="metadata[opens_at]" type="datetime-local" value="{{ $metaValue('opens_at') }}">
                                </label>
                                <label>
                                    <span>Closes at</span>
                                    <input name="metadata[closes_at]" type="datetime-local" value="{{ $metaValue('closes_at') }}">
                                </label>
                                <label>
                                    <span>Inventory</span>
                                    <input name="metadata[inventory]" type="number" min="0" value="{{ $metaValue('inventory') }}">
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset" data-type-fieldset="exclusive">
                            <legend>Exclusive</legend>
                            <div class="admin-form-grid">
                                <label>
                                    <span>Kind</span>
                                    <select name="metadata[exclusive_kind]">
                                        @foreach (['audio', 'video', 'photo', 'post', 'download'] as $kind)
                                            <option value="{{ $kind }}" @selected($metaValue('exclusive_kind') === $kind)>{{ $kind }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Expires at</span>
                                    <input name="metadata[expires_at]" type="datetime-local" value="{{ $metaValue('expires_at') }}">
                                </label>
                                <label class="admin-field-wide">
                                    <span>Access note</span>
                                    <textarea name="metadata[access_note]" rows="4">{{ $metaValue('access_note') }}</textarea>
                                </label>
                            </div>
                        </fieldset>
                    </section>

                    <section class="admin-panel" aria-labelledby="assets-title">
                        <div class="admin-section-head">
                            <div>
                                <p class="admin-kicker">Reuse</p>
                                <h2 id="assets-title">Assets and taxonomy</h2>
                            </div>
                        </div>

                        <div class="admin-form-grid admin-form-grid-wide">
                            <label class="admin-field-wide">
                                <span>Media assets</span>
                                <select name="media_asset_ids[]" multiple size="8">
                                    @foreach ($mediaAssets as $asset)
                                        <option value="{{ $asset->id }}" @selected(in_array((string) $asset->id, $selectedMediaIds, true))>
                                            {{ $asset->type->value }} - {{ $asset->title ?: $asset->original_filename }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="admin-field-wide">
                                <span>Taxonomy</span>
                                <select name="taxonomy_ids[]" multiple size="6">
                                    @foreach ($taxonomies as $taxonomy)
                                        <option value="{{ $taxonomy->id }}" @selected(in_array((string) $taxonomy->id, $selectedTaxonomyIds, true))>
                                            {{ $taxonomy->type->value }} - {{ $taxonomy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="admin-panel" aria-labelledby="windows-title">
                        <div class="admin-section-head">
                            <div>
                                <p class="admin-kicker">Release windows</p>
                                <h2 id="windows-title">Audience calendar</h2>
                            </div>
                        </div>

                        <div class="admin-window-grid">
                            @foreach ($releaseRows as $index => $window)
                                <div class="admin-window-row">
                                    <label>
                                        <span>Audience</span>
                                        <select name="release_windows[{{ $index }}][audience]">
                                            <option value="">None</option>
                                            @foreach ($visibilityAudiences as $audience)
                                                <option value="{{ $audience->value }}" @selected(($window['audience'] ?? '') === $audience->value)>
                                                    {{ $audience->value }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>Starts</span>
                                        <input name="release_windows[{{ $index }}][starts_at]" type="datetime-local" value="{{ $window['starts_at'] ?? '' }}">
                                    </label>
                                    <label>
                                        <span>Ends</span>
                                        <input name="release_windows[{{ $index }}][ends_at]" type="datetime-local" value="{{ $window['ends_at'] ?? '' }}">
                                    </label>
                                    <label>
                                        <span>Country</span>
                                        <input name="release_windows[{{ $index }}][country_codes][]" type="text" maxlength="2" value="{{ collect($window['country_codes'] ?? [])->first() }}">
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <div class="admin-sticky-actions">
                        <a class="admin-button admin-button-ghost" href="{{ route('admin.content.index') }}">Back</a>
                        <button class="admin-button admin-button-primary" name="action" value="draft" type="submit">Save draft</button>
                        <button class="admin-button admin-button-danger" name="action" value="schedule" type="submit" @disabled(! auth()->user()->canPublishContent())>Schedule</button>
                        <button class="admin-button admin-button-danger" name="action" value="publish" type="submit" @disabled(! auth()->user()->canPublishContent())>Publish</button>
                    </div>
                </form>
            </main>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const typeSelect = document.querySelector('#content-type');
                const fieldsets = Array.from(document.querySelectorAll('[data-type-fieldset]'));

                const syncTypeFields = () => {
                    fieldsets.forEach((fieldset) => {
                        const isActive = fieldset.dataset.typeFieldset === typeSelect.value;
                        fieldset.hidden = !isActive;

                        fieldset.querySelectorAll('input, select, textarea').forEach((field) => {
                            field.disabled = !isActive;
                        });
                    });
                };

                typeSelect.addEventListener('change', syncTypeFields);
                syncTypeFields();
            });
        </script>
    </body>
</html>
