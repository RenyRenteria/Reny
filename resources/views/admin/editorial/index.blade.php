<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Content CMS | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @php
            $editing = $content !== null;
            $metadata = old('metadata', $content?->metadata ?? []);
            $selectedMedia = array_map('intval', old('media_asset_ids', $selectedMediaIds));
            $scheduledValue = old('scheduled_at', $content?->scheduled_at?->timezone($panamaTimezone)->format('Y-m-d\TH:i'));
            $releaseWindowInput = old('release_windows');

            if (is_array($releaseWindowInput)) {
                foreach ($releaseWindowInput as $window) {
                    if (! isset($window['audience'], $releaseWindows[$window['audience']])) {
                        continue;
                    }

                    $releaseWindows[$window['audience']]['starts_at'] = $window['starts_at'] ?? null;
                    $releaseWindows[$window['audience']]['ends_at'] = $window['ends_at'] ?? null;
                }
            }
        @endphp

        <div class="admin-shell">
            <aside class="admin-sidebar" aria-label="Admin navigation">
                <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                    <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                </a>

                <nav class="admin-nav" aria-label="CMS sections">
                    <a href="{{ route('admin.dashboard') }}">Overview</a>
                    <a href="{{ route('admin.media.index') }}">Media</a>
                    <a class="is-active" href="{{ route('admin.editorial.index') }}">Content</a>
                    <a href="{{ route('admin.editorial.index') }}#schedule-panel">Schedule</a>
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
                        <h1>{{ $editing ? 'Edit content' : 'Content forms' }}</h1>
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

                <section class="admin-panel" aria-labelledby="type-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">V1 content types</p>
                            <h2 id="type-title">Choose a form</h2>
                        </div>
                    </div>

                    <div class="admin-type-grid">
                        @foreach ($forms as $type => $definition)
                            <a class="admin-type-card @if ($selectedType->value === $type) is-active @endif" href="{{ route('admin.editorial.index', ['type' => $type]) }}">
                                <strong>{{ $definition['label'] }}</strong>
                                <span>{{ $definition['description'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="admin-panel" aria-labelledby="form-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">{{ $selectedDefinition['label'] }}</p>
                            <h2 id="form-title">{{ $editing ? $content->title : 'New content' }}</h2>
                        </div>

                        @if ($editing)
                            <a class="admin-button admin-button-secondary" href="{{ route('admin.editorial.preview', $content) }}">Private preview</a>
                        @endif
                    </div>

                    <form class="admin-form-grid admin-editorial-form" method="POST" action="{{ $editing ? route('admin.editorial.update', $content) : route('admin.editorial.drafts.store') }}">
                        @csrf

                        @if ($editing)
                            <input name="content_id" type="hidden" value="{{ $content->id }}">
                        @endif

                        <input name="type" type="hidden" value="{{ $selectedType->value }}">

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
                                @foreach (\App\Enums\VisibilityAudience::cases() as $audience)
                                    <option value="{{ $audience->value }}" @selected(old('visibility', $content?->visibility->value ?? 'open') === $audience->value)>
                                        {{ str_replace('_', ' ', $audience->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Purchase key</span>
                            <input name="purchase_key" type="text" maxlength="120" value="{{ old('purchase_key', $content?->purchase_key) }}">
                        </label>

                        <label class="admin-field-wide">
                            <span>Summary</span>
                            <textarea name="summary" maxlength="500">{{ old('summary', $content?->summary) }}</textarea>
                        </label>

                        <label class="admin-field-wide">
                            <span>Body</span>
                            <textarea name="body">{{ old('body', $content?->body) }}</textarea>
                        </label>

                        @foreach ($selectedDefinition['fields'] as $field)
                            @php
                                $fieldValue = $metadata[$field['name']] ?? null;
                                $inputName = "metadata[{$field['name']}]";
                            @endphp

                            @if ($field['type'] === 'textarea')
                                <label class="admin-field-wide">
                                    <span>{{ $field['label'] }}</span>
                                    <textarea name="{{ $inputName }}" @required($field['required'] ?? false)>{{ is_scalar($fieldValue) ? $fieldValue : '' }}</textarea>
                                </label>
                            @elseif ($field['type'] === 'list')
                                <fieldset class="admin-field-wide admin-fieldset">
                                    <legend>{{ $field['label'] }}</legend>
                                    @php
                                        $optionValues = is_array($fieldValue) ? array_pad($fieldValue, 4, '') : ['', '', '', ''];
                                    @endphp

                                    @foreach ($optionValues as $optionValue)
                                        <input name="{{ $inputName }}[]" type="text" maxlength="160" value="{{ $optionValue }}" @required($loop->iteration <= 2)>
                                    @endforeach
                                </fieldset>
                            @else
                                <label>
                                    <span>{{ $field['label'] }}</span>
                                    <input
                                        name="{{ $inputName }}"
                                        type="{{ $field['type'] }}"
                                        @if (isset($field['step'])) step="{{ $field['step'] }}" @endif
                                        value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
                                        @required($field['required'] ?? false)
                                    >
                                </label>
                            @endif
                        @endforeach

                        <fieldset class="admin-field-wide admin-fieldset">
                            <legend>Reusable media</legend>

                            <div class="admin-media-picker">
                                @forelse ($mediaAssets as $asset)
                                    <label class="admin-media-option">
                                        <input name="media_asset_ids[]" type="checkbox" value="{{ $asset->id }}" @checked(in_array($asset->id, $selectedMedia, true))>
                                        <span>
                                            <strong>{{ $asset->title ?: $asset->original_filename }}</strong>
                                            <small>{{ $asset->type->value }} / {{ $asset->processing_status->value }}</small>
                                        </span>
                                    </label>
                                @empty
                                    <div class="admin-empty-state">Upload assets in Media before attaching reusable files.</div>
                                @endforelse
                            </div>
                        </fieldset>

                        <fieldset id="schedule-panel" class="admin-field-wide admin-fieldset">
                            <legend>Panama schedule</legend>

                            <label>
                                <span>Scheduled at</span>
                                <input name="scheduled_at" type="datetime-local" value="{{ $scheduledValue }}">
                            </label>

                            @foreach ($releaseWindows as $audience => $window)
                                <div class="admin-window-row">
                                    <input name="release_windows[{{ $loop->index }}][audience]" type="hidden" value="{{ $window['audience'] }}">

                                    <label>
                                        <span>{{ ucfirst($audience) }} starts</span>
                                        <input name="release_windows[{{ $loop->index }}][starts_at]" type="datetime-local" value="{{ $window['starts_at'] }}">
                                    </label>

                                    <label>
                                        <span>{{ ucfirst($audience) }} ends</span>
                                        <input name="release_windows[{{ $loop->index }}][ends_at]" type="datetime-local" value="{{ $window['ends_at'] }}">
                                    </label>
                                </div>
                            @endforeach
                        </fieldset>

                        <div class="admin-form-actions admin-field-wide">
                            <button class="admin-button admin-button-primary" type="submit">
                                {{ $editing ? 'Save changes' : 'Save draft' }}
                            </button>

                            <button
                                class="admin-button admin-button-danger"
                                type="submit"
                                formaction="{{ route('admin.editorial.publish') }}"
                                @disabled(! auth()->user()->canPublishContent())
                            >
                                Publish
                            </button>

                            <button
                                class="admin-button admin-button-secondary"
                                type="submit"
                                formaction="{{ route('admin.editorial.schedule') }}"
                                @disabled(! auth()->user()->canPublishContent())
                            >
                                Schedule
                            </button>
                        </div>
                    </form>
                </section>

                <section class="admin-panel" aria-labelledby="library-title">
                    <div class="admin-section-head">
                        <div>
                            <p class="admin-kicker">Editorial queue</p>
                            <h2 id="library-title">Recent content</h2>
                        </div>
                    </div>

                    <div class="admin-queue">
                        @forelse ($contents as $item)
                            <article class="admin-queue-item">
                                <div>
                                    <span>{{ str_replace('_', ' ', $item->type->value) }}</span>
                                    <strong>{{ $item->title }}</strong>
                                </div>

                                <div class="admin-badges">
                                    <span>{{ $item->status->value }}</span>
                                    <span>{{ $item->visibility->value }}</span>
                                    @if ($item->needs_approval)
                                        <span>needs approval</span>
                                    @endif
                                    <a href="{{ route('admin.editorial.edit', $item) }}">Edit</a>
                                    <a href="{{ route('admin.editorial.preview', $item) }}">Preview</a>
                                </div>
                            </article>
                        @empty
                            <div class="admin-empty-state">No editorial content yet.</div>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
