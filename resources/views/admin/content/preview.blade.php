@extends('admin.layout')

@section('title', 'Preview')
@section('admin_section', 'contenido')
@section('admin_theme', 'music')
@section('head')
    <meta name="robots" content="noindex,nofollow,noarchive">
@endsection

@section('content')
    <section class="admin-dashboard-section is-active">
        <div class="page-hero admin-hero-row">
            <div>
                <p class="admin-kicker">Private preview</p>
                <h1>{{ $content->title }}</h1>
                <p>{{ $content->summary ?: 'Preview privado para revisar antes de publicar.' }}</p>
            </div>
            <div class="admin-actions">
                <a class="admin-button admin-button-primary" href="{{ route('admin.content.edit', $content) }}">Edit</a>
                <a class="admin-button admin-button-ghost" href="{{ route('admin.content.index') }}">Content</a>
            </div>
        </div>

        <section class="admin-panel" aria-labelledby="preview-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">{{ str_replace('_', ' ', $content->type->value) }}</p>
                    <h2 id="preview-title">Contenido</h2>
                </div>
            </div>

            <div class="admin-preview-grid">
                <article class="admin-preview-body">
                    @if ($content->body)
                        {!! nl2br(e($content->body)) !!}
                    @else
                        <span class="admin-empty-state">No body content.</span>
                    @endif
                </article>

                <aside class="admin-preview-meta" aria-label="Content metadata">
                    <div><span>Status</span><strong>{{ $content->status->value }}</strong></div>
                    <div><span>Visibility</span><strong>{{ $content->visibility->value }}</strong></div>
                    <div>
                        <span>Schedule</span>
                        <strong>{{ $content->scheduled_at ? $content->scheduled_at->copy()->timezone($timezone)->format('M j, Y g:i A').' Panama' : 'None' }}</strong>
                    </div>
                    <div><span>Approval</span><strong>{{ $content->needs_approval ? 'Needs approval' : 'Cleared' }}</strong></div>
                </aside>
            </div>
        </section>

        <section class="admin-panel" aria-labelledby="metadata-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Schema</p>
                    <h2 id="metadata-title">Metadata</h2>
                </div>
            </div>

            @if ($content->metadata)
                <dl class="admin-definition-grid">
                    @foreach ($content->metadata as $key => $value)
                        <div>
                            <dt>{{ str_replace('_', ' ', $key) }}</dt>
                            <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="admin-empty-state">No metadata.</div>
            @endif
        </section>

        <section class="admin-panel" aria-labelledby="release-windows-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Scheduling</p>
                    <h2 id="release-windows-title">Release windows</h2>
                </div>
            </div>

            <div class="admin-media-list">
                @forelse ($content->releaseWindows as $window)
                    <article class="admin-media-row">
                        <div>
                            <span>{{ $window->audience->value }}</span>
                            <strong>{{ $window->starts_at?->copy()->timezone($timezone)->format('M j, Y g:i A') ?? 'Always open' }}</strong>
                            <small>Ends {{ $window->ends_at?->copy()->timezone($timezone)->format('M j, Y g:i A') ?? 'without end date' }}</small>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty-state">No release windows.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-panel" aria-labelledby="attached-title">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Reuse</p>
                    <h2 id="attached-title">Attached assets</h2>
                </div>
            </div>

            <div class="admin-media-list">
                @forelse ($content->mediaAssets as $asset)
                    <article class="admin-media-row">
                        <div>
                            <span>{{ $asset->type->value }}</span>
                            <strong>{{ $asset->title ?: $asset->original_filename }}</strong>
                            <small>{{ $asset->is_public ? 'public' : 'private' }}</small>
                        </div>

                        <div class="admin-badges">
                            <span>{{ $asset->processing_status->value }}</span>
                            <span>{{ $asset->pivot->role }}</span>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty-state">No attached assets.</div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
