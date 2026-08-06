@php
    $communityUrls = [
        'post' => route('admin.site-editor.show', ['page' => 'community', 'community_section' => 'post']),
        'members' => route('admin.site-editor.show', ['page' => 'community', 'community_section' => 'members']),
        'rsvp' => route('admin.site-editor.show', ['page' => 'community', 'community_section' => 'rsvp']),
    ];
@endphp

<div class="community-cms" data-community-cms>
    <nav class="community-cms-tabs" aria-label="Community CMS sections">
        <a @class(['is-active' => $communitySection === 'post']) href="{{ $communityUrls['post'] }}" @if ($communitySection === 'post') aria-current="page" @endif>Post</a>
        <a @class(['is-active' => $communitySection === 'members']) href="{{ $communityUrls['members'] }}" @if ($communitySection === 'members') aria-current="page" @endif>Members</a>
        <a @class(['is-active' => $communitySection === 'rsvp']) href="{{ $communityUrls['rsvp'] }}" @if ($communitySection === 'rsvp') aria-current="page" @endif>RSVP List</a>
    </nav>

    @if ($communitySection === 'post' && $communityPostForm)
        @php
            $posts = $communityPostForm['posts'];
            $comments = $communityPostForm['comments'];
            $timezone = $communityPostForm['timezone'];
            $newBody = \App\Support\CommunityPostContent::sanitize((string) old('body', ''));
        @endphp

        <section class="community-cms-section community-posts-cms" data-community-panel="post">
            <div class="admin-page-heading">
                <div>
                    <p class="admin-kicker">Community / Post</p>
                    <h1>Royal Posts</h1>
                    <p>El feed usa exactamente el mismo contenido que la página pública de Royal.</p>
                </div>
                <div class="admin-actions">
                    <button class="admin-button admin-button-primary" type="button" data-community-post-modal-open>+ New Post</button>
                    <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Open Royal</a>
                </div>
            </div>

            <section class="admin-panel community-live-preview" aria-labelledby="community-live-preview-title">
                <div class="site-editor-preview-toolbar">
                    <div>
                        <p class="admin-kicker">Royal live view</p>
                        <h2 id="community-live-preview-title">Public Royal tab</h2>
                        <span>Posts publicados se muestran desde la misma base de datos.</span>
                    </div>
                    <div class="admin-actions">
                        <span class="admin-status-pill admin-status-success">Synced</span>
                        <button class="admin-button admin-button-soft" type="button" data-community-preview-refresh>Refresh</button>
                    </div>
                </div>
                <div class="site-editor-browser-frame">
                    <iframe
                        title="Royal public tab"
                        src="{{ $publicUrl }}"
                        data-community-live-preview
                        loading="eager"
                    ></iframe>
                </div>
            </section>

            <section class="community-manage-section" id="community-manage-posts">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-kicker">Posts</p>
                        <h2>Manage posts</h2>
                        <p class="admin-panel-copy">Edita, programa, guarda drafts o elimina publicaciones.</p>
                    </div>
                    <span class="admin-status-pill admin-status-info">{{ $posts->count() }} total</span>
                </div>

                <div class="community-post-admin-list">
                    @forelse ($posts as $post)
                        @php
                            $coverId = (int) data_get($post->metadata ?? [], 'image_asset_id');
                            $cover = $post->mediaAssets->firstWhere('id', $coverId)
                                ?? $post->mediaAssets->first(fn ($asset) => $asset->pivot?->role === 'cover');
                            $attachments = $post->mediaAssets
                                ->filter(fn ($asset) => $asset->pivot?->role === 'attachment')
                                ->values();
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
                                    <label><span>Title</span><input name="title" type="text" maxlength="160" value="{{ $post->title }}" required></label>
                                    <label><span>Post date</span><input name="published_on" type="date" value="{{ $publishedOn }}" required></label>
                                    <label><span>Replace cover</span><input name="cover_image" type="file" accept="image/avif,image/jpeg,image/png,image/webp"></label>
                                    <label>
                                        <span>Add photos or videos</span>
                                        <input
                                            name="attachments[]"
                                            type="file"
                                            accept="image/avif,image/gif,image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                                            data-community-attachments
                                            data-max-video-bytes="{{ config('media.types.'.\App\Enums\MediaAssetType::Video->value.'.max_bytes') }}"
                                            multiple
                                        >
                                        <small>Up to 12 files total. Videos: 1 GB maximum each. Stored on this server.</small>
                                    </label>
                                    <label><span>Schedule for</span><input name="scheduled_at" type="datetime-local" value="{{ $scheduledAt }}"></label>
                                </div>

                                @if ($cover?->publicUrl())
                                    <div class="community-current-cover">
                                        <img src="{{ $cover->publicUrl() }}" alt="Current cover for {{ $post->title }}">
                                        <label class="admin-checkbox">
                                            <input name="remove_cover" type="hidden" value="0">
                                            <input name="remove_cover" type="checkbox" value="1">
                                            <span>Remove cover</span>
                                        </label>
                                    </div>
                                @endif

                                @if ($attachments->isNotEmpty())
                                    <div class="community-current-attachments" aria-label="Current post attachments">
                                        @foreach ($attachments as $attachment)
                                            @php($attachmentUrl = $attachment->publicUrl())
                                            <label class="community-current-attachment">
                                                @if ($attachmentUrl && $attachment->type === \App\Enums\MediaAssetType::Image)
                                                    <img src="{{ $attachmentUrl }}" alt="{{ $attachment->alt_text ?: $post->title }}">
                                                @elseif ($attachmentUrl && $attachment->type === \App\Enums\MediaAssetType::Video)
                                                    <video controls preload="metadata">
                                                        <source src="{{ $attachmentUrl }}" type="{{ $attachment->mime_type }}">
                                                    </video>
                                                @endif
                                                <span title="{{ $attachment->original_filename }}">{{ $attachment->original_filename }}</span>
                                                <span class="admin-checkbox">
                                                    <input name="remove_attachment_ids[]" type="checkbox" value="{{ $attachment->id }}">
                                                    <span>Remove</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif

                                @include('admin.site-editor.partials.community-rich-editor', [
                                    'bodyHtml' => \App\Support\CommunityPostContent::sanitize((string) $post->body),
                                    'editorId' => 'community-body-'.$post->id,
                                ])

                                <label class="community-media-urls">
                                    <span>Images, video, audio, embeds and links</span>
                                    <textarea name="media_urls" rows="5">{{ $mediaUrls }}</textarea>
                                    <small>One URL per line.</small>
                                </label>

                                <label class="admin-checkbox community-comments-toggle">
                                    <input name="comments_enabled" type="hidden" value="0">
                                    <input name="comments_enabled" type="checkbox" value="1" @checked($commentsEnabled)>
                                    <span>Allow comments with login</span>
                                </label>

                                <div class="community-post-submit-row">
                                    <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Save draft</button>
                                    <button class="admin-button admin-button-warning" name="action" value="schedule" type="submit">Schedule</button>
                                    <button class="admin-button admin-button-primary" name="action" value="publish" type="submit">Publish now</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.site-editor.community-posts.destroy', $post) }}" onsubmit="return confirm('Delete this post and all its interactions?')">
                                @csrf
                                @method('DELETE')
                                <button class="admin-button admin-button-danger" type="submit">Delete post</button>
                            </form>
                        </details>
                    @empty
                        <div class="admin-empty-state">No posts yet. Create the first one with New Post.</div>
                    @endforelse
                </div>
            </section>

            <details class="admin-panel community-secondary-panel">
                <summary>
                    <span>Comment moderation</span>
                    <span class="admin-status-pill admin-status-info">{{ $comments->count() }}</span>
                </summary>
                <div class="community-comment-admin-list">
                    @forelse ($comments as $comment)
                        <article @class(['is-removed' => $comment->status !== 'visible'])>
                            <div>
                                <strong>{{ $comment->user?->name ?? $comment->user?->username ?? 'User' }}</strong>
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
                                        <button class="admin-button admin-button-warning" type="submit">Hide</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.site-editor.community-comments.moderate', $comment) }}" onsubmit="return confirm('Delete this comment permanently?')">
                                    @csrf
                                    @method('PATCH')
                                    <input name="action" type="hidden" value="delete">
                                    <button class="admin-button admin-button-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="admin-empty-state">No comments yet.</div>
                    @endforelse
                </div>
            </details>

            @if ($pageSettings && $pageSettingsForm)
                <details class="admin-panel community-secondary-panel">
                    <summary><span>Royal page settings and SEO</span></summary>
                    @include('admin.site-editor.page-settings')
                </details>
            @endif
        </section>

        <dialog
            class="community-post-modal"
            data-community-post-modal
            data-open-on-load="{{ $errors->any() && old('post_form') === 'new' ? 'true' : 'false' }}"
            aria-labelledby="community-new-post-title"
        >
            <div class="community-post-modal-card">
                <div class="community-post-modal-head">
                    <div>
                        <p class="admin-kicker">New Post</p>
                        <h2 id="community-new-post-title">Create a Royal post</h2>
                    </div>
                    <button type="button" class="community-post-modal-close" data-community-post-modal-close aria-label="Close new post modal">×</button>
                </div>

                <form
                    class="community-post-form"
                    method="POST"
                    action="{{ route('admin.site-editor.community-posts.store') }}"
                    enctype="multipart/form-data"
                    data-community-post-form
                >
                    @csrf
                    <input name="post_form" type="hidden" value="new">

                    <div class="community-post-form-grid">
                        <label>
                            <span>Title</span>
                            <input name="title" type="text" maxlength="160" value="{{ old('title') }}" required>
                        </label>
                        <label>
                            <span>Post date</span>
                            <input name="published_on" type="date" value="{{ old('published_on', now($timezone)->toDateString()) }}" required>
                        </label>
                        <label>
                            <span>Cover image (optional)</span>
                            <input name="cover_image" type="file" accept="image/avif,image/jpeg,image/png,image/webp">
                        </label>
                        <label>
                            <span>Photos or videos (optional)</span>
                            <input
                                name="attachments[]"
                                type="file"
                                accept="image/avif,image/gif,image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                                data-community-attachments
                                data-max-video-bytes="{{ config('media.types.'.\App\Enums\MediaAssetType::Video->value.'.max_bytes') }}"
                                multiple
                            >
                            <small>Up to 12 files. Videos: 1 GB maximum each. Stored on this server.</small>
                        </label>
                        <label>
                            <span>Schedule for</span>
                            <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}">
                        </label>
                    </div>

                    @include('admin.site-editor.partials.community-rich-editor', [
                        'bodyHtml' => $newBody,
                        'editorId' => 'community-new-body',
                    ])

                    <label class="community-media-urls">
                        <span>Images, video, audio, embeds and links</span>
                        <textarea name="media_urls" rows="4" placeholder="One URL per line">{{ old('media_urls') }}</textarea>
                    </label>

                    <label class="admin-checkbox community-comments-toggle">
                        <input name="comments_enabled" type="hidden" value="0">
                        <input name="comments_enabled" type="checkbox" value="1" @checked(old('comments_enabled', '1') === '1')>
                        <span>Allow comments with login</span>
                    </label>

                    <div class="community-post-submit-row">
                        <button class="admin-button admin-button-soft" name="action" value="draft" type="submit">Save draft</button>
                        <button class="admin-button admin-button-warning" name="action" value="schedule" type="submit">Schedule</button>
                        <button class="admin-button admin-button-primary" name="action" value="publish" type="submit">Publish now</button>
                    </div>
                </form>
            </div>
        </dialog>
    @elseif ($communitySection === 'members' && $communityMembers)
        <section class="community-cms-section" data-community-panel="members">
            @include('admin.site-editor.partials.community-members', ['communityMembers' => $communityMembers])
        </section>
    @elseif ($communitySection === 'rsvp' && $communityRsvps)
        <section class="community-cms-section" data-community-panel="rsvp">
            @include('admin.site-editor.partials.community-rsvps', ['communityRsvps' => $communityRsvps])
        </section>
    @endif
</div>
