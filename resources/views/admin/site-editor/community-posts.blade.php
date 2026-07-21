@php
    $posts = $communityPostForm['posts'];
    $comments = $communityPostForm['comments'];
    $timezone = $communityPostForm['timezone'];
    $newBody = \App\Support\CommunityPostContent::sanitize((string) old('body', ''));
@endphp

<div class="community-posts-cms" data-community-posts-cms>
    <div class="admin-page-heading">
        <div>
            <h1>Community Posts</h1>
            <p>Crea, programa y modera el feed público de Community.</p>
        </div>
        <div class="admin-actions">
            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
            <a class="admin-button admin-button-soft" href="{{ $previewUrl }}" target="_blank" rel="noreferrer">Preview guest</a>
        </div>
    </div>

    <nav class="site-editor-page-tabs" aria-label="Site editor pages">
        @foreach ($pages as $key => $page)
            <a @class(['is-active' => $key === $activePage]) href="{{ route('admin.site-editor.show', ['page' => $key]) }}">
                <span>{{ $page['label'] }}</span>
                <small>{{ $page['public_path'] }}</small>
            </a>
        @endforeach
    </nav>

    @unless ($communityPostForm['can_manage'])
        <section class="admin-alert-card admin-alert-warning">
            <div>
                <span class="admin-alert-symbol">!</span>
                <div>
                    <h2>Módulo restringido</h2>
                    <p>Solo {{ $communityPostForm['editor_email'] }} puede crear, editar, programar, publicar o moderar posts.</p>
                </div>
            </div>
        </section>
    @else
        <nav class="community-cms-tabs" aria-label="Community CMS sections">
            <a href="#community-new-post">Nuevo post</a>
            <a href="#community-manage-posts">Posts ({{ $posts->count() }})</a>
            <a href="#community-comments">Comentarios ({{ $comments->count() }})</a>
            <a href="#community-rsvps">RSVP</a>
        </nav>

        <section class="admin-panel community-post-editor-panel" id="community-new-post">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Nuevo post</p>
                    <h2>Publicar en el feed</h2>
                    <p class="admin-panel-copy">El post completo aparece dentro del feed. La imagen de portada es opcional.</p>
                </div>
                <span class="admin-status-pill admin-status-info">Solo Reny</span>
            </div>

            <form
                class="community-post-form"
                method="POST"
                action="{{ route('admin.site-editor.community-posts.store') }}"
                enctype="multipart/form-data"
                data-community-post-form
            >
                @csrf

                <div class="community-post-form-grid">
                    <label>
                        <span>Título</span>
                        <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
                    </label>
                    <label>
                        <span>Fecha del post</span>
                        <input name="published_on" type="date" value="{{ old('published_on', now($timezone)->toDateString()) }}" required>
                    </label>
                    <label>
                        <span>Imagen de portada (opcional)</span>
                        <input name="cover_image" type="file" accept="image/avif,image/jpeg,image/png,image/webp">
                    </label>
                    <label>
                        <span>Programar para</span>
                        <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}">
                    </label>
                </div>

                @include('admin.site-editor.partials.community-rich-editor', [
                    'bodyHtml' => $newBody,
                    'editorId' => 'community-new-body',
                ])

                <label class="community-media-urls">
                    <span>Imágenes, video, audio, embeds y links</span>
                    <textarea name="media_urls" rows="5" placeholder="Pega una URL por línea. Soporta YouTube, Vimeo, Spotify y archivos directos de imagen, video o audio.">{{ old('media_urls') }}</textarea>
                    <small>Los enlaces se muestran debajo del contenido, dentro del mismo post.</small>
                </label>

                <label class="admin-checkbox community-comments-toggle">
                    <input name="comments_enabled" type="hidden" value="0">
                    <input name="comments_enabled" type="checkbox" value="1" @checked(old('comments_enabled', '1') === '1')>
                    <span>Permitir comentarios con login</span>
                </label>

                <div class="community-post-submit-row">
                    <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Guardar borrador</button>
                    <button class="admin-button admin-button-warning" name="action" value="schedule" type="submit">Programar</button>
                    <button class="admin-button admin-button-primary" name="action" value="publish" type="submit">Publicar ahora</button>
                </div>
            </form>
        </section>

        <section class="community-manage-section" id="community-manage-posts">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Feed</p>
                    <h2>Administrar posts</h2>
                </div>
            </div>

            <div class="community-post-admin-list">
                @forelse ($posts as $post)
                    @php
                        $coverId = (int) data_get($post->metadata ?? [], 'image_asset_id');
                        $cover = $post->mediaAssets->firstWhere('id', $coverId) ?? $post->mediaAssets->first();
                        $mediaUrls = collect(data_get($post->metadata ?? [], 'media_items', []))->pluck('url')->filter()->implode("\n");
                        $publishedOn = data_get($post->metadata ?? [], 'published_on')
                            ?: ($post->published_at ?? $post->scheduled_at ?? $post->created_at)?->timezone($timezone)->toDateString();
                        $scheduledAt = $post->scheduled_at?->timezone($timezone)->format('Y-m-d\TH:i');
                        $commentsEnabled = (bool) data_get($post->metadata ?? [], 'comments_enabled', true);
                    @endphp
                    <details class="admin-panel community-post-admin-card" @if ((string) request('edit') === (string) $post->id) open @endif>
                        <summary>
                            <div>
                                <strong>{{ $post->title }}</strong>
                                <span>{{ $publishedOn }} · {{ $post->status->value }}</span>
                            </div>
                            <span @class([
                                'admin-status-pill',
                                'admin-status-success' => $post->status->value === 'published',
                                'admin-status-info' => $post->status->value === 'scheduled',
                                'admin-status-warning' => $post->status->value === 'draft',
                            ])>{{ $post->status->value }}</span>
                        </summary>

                        <form
                            class="community-post-form"
                            method="POST"
                            action="{{ route('admin.site-editor.community-posts.update', $post) }}"
                            enctype="multipart/form-data"
                            data-community-post-form
                        >
                            @csrf
                            @method('PATCH')

                            <div class="community-post-form-grid">
                                <label><span>Título</span><input name="title" type="text" maxlength="160" value="{{ $post->title }}" required></label>
                                <label><span>Fecha del post</span><input name="published_on" type="date" value="{{ $publishedOn }}" required></label>
                                <label><span>Reemplazar portada</span><input name="cover_image" type="file" accept="image/avif,image/jpeg,image/png,image/webp"></label>
                                <label><span>Programar para</span><input name="scheduled_at" type="datetime-local" value="{{ $scheduledAt }}"></label>
                            </div>

                            @if ($cover?->publicUrl())
                                <div class="community-current-cover">
                                    <img src="{{ $cover->publicUrl() }}" alt="Portada actual de {{ $post->title }}">
                                    <label class="admin-checkbox">
                                        <input name="remove_cover" type="hidden" value="0">
                                        <input name="remove_cover" type="checkbox" value="1">
                                        <span>Quitar portada</span>
                                    </label>
                                </div>
                            @endif

                            @include('admin.site-editor.partials.community-rich-editor', [
                                'bodyHtml' => \App\Support\CommunityPostContent::sanitize((string) $post->body),
                                'editorId' => 'community-body-'.$post->id,
                            ])

                            <label class="community-media-urls">
                                <span>Imágenes, video, audio, embeds y links</span>
                                <textarea name="media_urls" rows="5">{{ $mediaUrls }}</textarea>
                                <small>Una URL por línea.</small>
                            </label>

                            <label class="admin-checkbox community-comments-toggle">
                                <input name="comments_enabled" type="hidden" value="0">
                                <input name="comments_enabled" type="checkbox" value="1" @checked($commentsEnabled)>
                                <span>Permitir comentarios con login</span>
                            </label>

                            <div class="community-post-submit-row">
                                <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Guardar borrador</button>
                                <button class="admin-button admin-button-warning" name="action" value="schedule" type="submit">Programar</button>
                                <button class="admin-button admin-button-primary" name="action" value="publish" type="submit">Publicar ahora</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.site-editor.community-posts.destroy', $post) }}" onsubmit="return confirm('¿Eliminar este post y todas sus interacciones?')">
                            @csrf
                            @method('DELETE')
                            <button class="admin-button admin-button-danger" type="submit">Eliminar post</button>
                        </form>
                    </details>
                @empty
                    <div class="admin-empty-state">El feed está vacío. Crea el primer post arriba.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-panel" id="community-comments">
            <div class="admin-section-head">
                <div>
                    <p class="admin-kicker">Moderación</p>
                    <h2>Comentarios</h2>
                    <p class="admin-panel-copy">Oculta comentarios del feed o elimínalos permanentemente.</p>
                </div>
            </div>

            <div class="community-comment-admin-list">
                @forelse ($comments as $comment)
                    <article @class(['is-removed' => $comment->status !== 'visible'])>
                        <div>
                            <strong>{{ $comment->user?->name ?? $comment->user?->username ?? 'Usuario' }}</strong>
                            <span>{{ $comment->user?->email }} · {{ $comment->created_at?->timezone($timezone)->format('M j, Y g:i A') }}</span>
                            <p>{{ $comment->body }}</p>
                            <small>{{ $comment->post_key }} · {{ $comment->status }}</small>
                        </div>
                        <div class="admin-actions">
                            @if ($comment->status === 'visible')
                                <form method="POST" action="{{ route('admin.site-editor.community-comments.moderate', $comment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input name="action" type="hidden" value="hide">
                                    <button class="admin-button admin-button-warning" type="submit">Ocultar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.site-editor.community-comments.moderate', $comment) }}" onsubmit="return confirm('¿Eliminar este comentario permanentemente?')">
                                @csrf
                                @method('PATCH')
                                <input name="action" type="hidden" value="delete">
                                <button class="admin-button admin-button-danger" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty-state">Todavía no hay comentarios.</div>
                @endforelse
            </div>
        </section>

    @endunless

    <section id="community-rsvps">
        @if ($communityRsvps)
            @include('admin.site-editor.partials.community-rsvps', ['communityRsvps' => $communityRsvps])
        @endif
    </section>
</div>
