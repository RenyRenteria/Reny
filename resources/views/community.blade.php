@php
    $community = $community ?? [];
    $communityPosts = $community['posts'] ?? [];
    $communityPoll = $community['poll'] ?? null;
    $canVoteOnPoll = (bool) ($communityPoll['can_vote'] ?? false);
    $pageSettings = $publicCms['page'] ?? [];
    $liveChat = $community['live_chat'] ?? [];
    $canUseCommunityActions = (bool) ($community['can_use_actions'] ?? false);
    $canUsePostActions = (bool) ($community['can_use_post_actions'] ?? false);
    $communityGateHref = auth()->check() ? route('store') : route('login');
    $communityGateCta = auth()->check() ? 'Get your Royal Pass' : 'Sign in';
    $communityGateTitle = auth()->check() ? 'Royal Pass requerido' : 'Inicia sesión para escribir';
    $reactionGateLabel = auth()->check() ? 'Obtén Royal Pass para reaccionar' : 'Inicia sesión para reaccionar';
    $commentGateLabel = auth()->check() ? 'Obtén Royal Pass para comentar' : 'Inicia sesión para comentar';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.public-seo', ['seo' => $pageSettings, 'fallbackTitle' => 'Royals | Reny Renteria'])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="community">
        <div class="community-shell home-shell royals-shell" data-public-page-root>
            <div class="stage-lights" aria-hidden="true">
                <span class="stage-light stage-light--one"></span>
                <span class="stage-light stage-light--two"></span>
                <span class="stage-light stage-light--three"></span>
                <span class="stage-light-fixture stage-light-fixture--one"></span>
                <span class="stage-light-fixture stage-light-fixture--two"></span>
                <span class="stage-light-fixture stage-light-fixture--three"></span>
            </div>

            @include('partials.cms-preview-banner')
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo-white.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation active="royals" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content community-content" id="royals">
                <header class="mobile-header community-mobile-header">
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo-white.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>
                    <span class="community-live-status"><span aria-hidden="true"></span> En vivo</span>
                </header>

                <x-royal-pass-banner :show-images="false" />

                <nav class="community-mobile-tabs" role="tablist" aria-label="Secciones de Royals" aria-orientation="horizontal">
                    <button
                        class="is-active"
                        id="communityFeedTab"
                        type="button"
                        role="tab"
                        aria-selected="true"
                        aria-controls="communityFeedPanel"
                        tabindex="0"
                        lang="en"
                        data-community-tab="feed"
                    >
                        Posts
                    </button>
                    <button
                        id="communityChatTab"
                        type="button"
                        role="tab"
                        aria-selected="false"
                        aria-controls="communityChatPanel"
                        tabindex="-1"
                        lang="en"
                        data-community-tab="chat"
                    >
                        Chat <span>Live</span>
                    </button>
                </nav>

                <div class="community-experience">
                    <section
                        class="community-feed-panel"
                        id="communityFeedPanel"
                        role="tabpanel"
                        aria-labelledby="communityFeedTab"
                        aria-label="Posts oficiales de Reny"
                        tabindex="0"
                        data-community-panel="feed"
                    >
                        <div class="community-welcome-card">
                            <h1>Directo de Reny. Cerca de la comunidad.</h1>
                        </div>

                        @if ($communityPoll)
                            <section
                                class="poll-card"
                                data-community-poll="{{ $communityPoll['key'] }}"
                                data-vote-endpoint="{{ $communityPoll['vote_endpoint'] }}"
                                data-voted="{{ empty($communityPoll['user_vote']) ? 'false' : 'true' }}"
                                aria-labelledby="community-poll-question"
                            >
                                <div class="poll-card-head">
                                    <h3 id="community-poll-question">{{ $communityPoll['question'] }}</h3>
                                    <span data-poll-total>{{ $communityPoll['total_votes_label'] }}</span>
                                </div>
                                <div class="poll-options">
                                    @foreach ($communityPoll['options'] as $option)
                                        @if ($canVoteOnPoll && empty($communityPoll['user_vote']))
                                            <button
                                                class="poll-option"
                                                type="button"
                                                data-percent="{{ $option['percent'] }}"
                                                data-option-key="{{ $option['key'] }}"
                                                data-option-label="{{ $option['label'] }}"
                                            >
                                                <span class="poll-option-top"><span>{{ $option['label'] }}</span><strong>{{ $option['percent'] }}%</strong></span>
                                                <span class="poll-meter"><span style="width: {{ $option['percent'] }}%"></span></span>
                                            </button>
                                        @else
                                            <a class="poll-option @if (($communityPoll['user_vote'] ?? null) === $option['key']) is-voted @endif" href="{{ $canVoteOnPoll ? '#' : $communityGateHref }}" data-percent="{{ $option['percent'] }}" @if ($canVoteOnPoll) aria-disabled="true" @endif>
                                                <span class="poll-option-top"><span>{{ $option['label'] }}</span><strong>{{ $option['percent'] }}%</strong></span>
                                                <span class="poll-meter"><span style="width: {{ $option['percent'] }}%"></span></span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                                @if (! $canVoteOnPoll)<small>{{ $communityGateCta }} to vote</small>@endif
                            </section>
                        @endif

                        <div class="community-feed-heading">
                            <h2>Posts de Reny</h2>
                            <span><b aria-hidden="true">✓</b> Cuenta oficial</span>
                        </div>

                        @forelse ($communityPosts as $post)
                            <article class="community-post-card" id="{{ $post['key'] }}" data-title="{{ $post['title'] }}">
                                <header class="community-post-head">
                                    <div class="community-reny-avatar" aria-hidden="true">
                                        <span>R</span>
                                        @if ($post['avatar_url'] ?? null)
                                            <img src="{{ $post['avatar_url'] }}" alt="" data-community-post-avatar>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>Reny <span aria-label="Cuenta verificada">✓</span></strong>
                                        <small>@renyoficial · {{ $post['time'] }}</small>
                                    </div>
                                </header>

                                <h3>{{ $post['title'] }}</h3>
                                <div class="community-post-copy">{!! $post['body_html'] !!}</div>

                                @if (! empty($post['image_url']))
                                    <div class="community-post-media">
                                        <img src="{{ $post['image_url'] }}" alt="{{ $post['image_alt'] }}">
                                    </div>
                                @endif

                                @if (! empty($post['media_items']))
                                    <div class="community-post-embeds">
                                        @foreach ($post['media_items'] as $media)
                                            @if ($media['type'] === 'image')
                                                <img src="{{ $media['url'] }}" alt="Contenido visual de {{ $post['title'] }}" loading="lazy">
                                            @elseif ($media['type'] === 'video')
                                                <video controls preload="metadata">
                                                    <source src="{{ $media['url'] }}">
                                                </video>
                                            @elseif ($media['type'] === 'audio')
                                                <audio controls preload="metadata">
                                                    <source src="{{ $media['url'] }}">
                                                </audio>
                                            @elseif ($media['type'] === 'embed')
                                                <iframe
                                                    src="{{ $media['embed_url'] }}"
                                                    title="{{ $media['label'] }} en {{ $post['title'] }}"
                                                    loading="lazy"
                                                    allow="autoplay; encrypted-media; picture-in-picture"
                                                    allowfullscreen
                                                ></iframe>
                                            @else
                                                <a href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer nofollow">
                                                    {{ $media['label'] }} <span aria-hidden="true">↗</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                <div class="community-post-actions">
                                    <div>
                                        @if ($canUsePostActions)
                                            <button
                                                class="community-post-action reaction-button @if ($post['liked']) is-reacted @endif"
                                                type="button"
                                                data-community-like
                                                data-endpoint="{{ $post['like_endpoint'] }}"
                                                data-count="{{ $post['like_count'] }}"
                                                data-analytics-id="{{ $post['key'] }}"
                                                data-analytics-type="reaction"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path></svg>
                                                <span class="reaction-count">{{ $post['like_count'] }}</span>
                                            </button>
                                        @else
                                            <a class="community-post-action community-action-gate" href="{{ $communityGateHref }}" aria-label="{{ $reactionGateLabel }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path></svg>
                                                {{ $post['like_count'] }}
                                            </a>
                                        @endif

                                        <span class="community-post-action" data-reply-count="{{ $post['key'] }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path></svg>
                                            <span>{{ $post['reply_count'] }} respuestas</span>
                                        </span>
                                    </div>
                                    <button
                                        class="community-post-action share"
                                        type="button"
                                        data-share-url="{{ $post['share_url'] }}"
                                        data-share-title="{{ $post['title'] }}"
                                        data-analytics-id="{{ $post['key'] }}"
                                        data-analytics-type="post"
                                        aria-label="Compartir {{ $post['title'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.6 13.5 6.8 4"></path><path d="m15.4 6.5-6.8 4"></path></svg>
                                        Compartir
                                    </button>
                                </div>

                                @if ($post['comments_enabled'])
                                    @if (! empty($post['replies']))
                                        <section class="community-post-comments" aria-label="Comentarios de {{ $post['title'] }}">
                                            @foreach ($post['replies'] as $reply)
                                                <article>
                                                    <header><strong>{{ $reply['author'] }}</strong><time>{{ $reply['time'] }}</time></header>
                                                    <p>{{ $reply['body'] }}</p>
                                                </article>
                                            @endforeach
                                        </section>
                                    @endif

                                    @if ($canUsePostActions)
                                        <form
                                            class="community-inline-reply community-reply-form"
                                            data-community-reply-form
                                            data-endpoint="{{ $post['reply_endpoint'] }}"
                                            data-post-key="{{ $post['key'] }}"
                                        >
                                            <label class="sr-only" for="reply-{{ $post['key'] }}">Responder a {{ $post['title'] }}</label>
                                            <input id="reply-{{ $post['key'] }}" name="body" type="text" maxlength="500" placeholder="Escribe un comentario...">
                                            <button type="submit">Comentar</button>
                                            <p class="community-form-status" data-form-status></p>
                                        </form>
                                    @else
                                        <a class="community-inline-gate" href="{{ $communityGateHref }}">{{ $commentGateLabel }}</a>
                                    @endif
                                @else
                                    <p class="community-comments-disabled">Los comentarios están desactivados para este post.</p>
                                @endif
                            </article>
                        @empty
                            <div class="community-empty-state">
                                <strong>Próximamente</strong>
                                <p>Los nuevos posts oficiales de Reny aparecerán aquí.</p>
                            </div>
                        @endforelse
                    </section>

                    <section
                        class="community-live-chat-panel"
                        id="communityChatPanel"
                        role="tabpanel"
                        aria-labelledby="communityChatTab"
                        aria-label="Live Chat"
                        tabindex="0"
                        data-community-panel="chat"
                        data-community-live-chat
                        data-messages-endpoint="{{ $liveChat['messages_endpoint'] ?? '' }}"
                        data-current-user-id="{{ $liveChat['current_user_id'] ?? '' }}"
                    >
                        <section class="community-live-chat-card">
                            <header class="community-chat-head">
                                <div>
                                    <div><h2 lang="en">Live Chat</h2><span>En vivo</span></div>
                                    <p data-live-chat-status>Actualización automática · chat moderado</p>
                                </div>
                                <span class="community-live-pulse" aria-label="Chat activo"></span>
                            </header>

                            <div class="community-pinned-message">
                                <strong>Mensaje fijado</strong>
                                <p>{{ $liveChat['pinned_message'] ?? '' }}</p>
                            </div>

                            <div class="community-live-messages" data-live-chat-messages aria-live="polite">
                                @forelse (($liveChat['messages'] ?? []) as $message)
                                    <article
                                        @class([
                                            'community-live-message',
                                            'is-self' => $message['is_self'],
                                            'is-host' => $message['is_host'],
                                        ])
                                        data-chat-message-id="{{ $message['id'] }}"
                                        data-chat-user-id="{{ $message['user_id'] }}"
                                    >
                                        <div class="community-chat-avatar" aria-hidden="true">
                                            <span>{{ $message['initials'] }}</span>
                                            @if ($message['avatar_url'] ?? null)
                                                <img src="{{ $message['avatar_url'] }}" alt="" data-live-chat-avatar-image>
                                            @endif
                                        </div>
                                        <div>
                                            <header>
                                                <strong>{{ $message['author'] }}</strong>
                                                @if ($message['is_host'])
                                                    <span lang="en">Host</span>
                                                @endif
                                                <time>{{ $message['time'] }}</time>
                                            </header>
                                            <p>{{ $message['text'] }}</p>
                                            @if ($message['block_endpoint'] || $message['moderation_endpoint'])
                                                <div class="community-live-message-actions">
                                                    @if ($message['block_endpoint'])
                                                        <button type="button" data-chat-block-endpoint="{{ $message['block_endpoint'] }}">Bloquear</button>
                                                    @endif
                                                    @if ($message['moderation_endpoint'])
                                                        <button type="button" data-chat-moderate-endpoint="{{ $message['moderation_endpoint'] }}">Ocultar</button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <div class="community-chat-empty" data-live-chat-empty>
                                        <strong>El chat está listo</strong>
                                        <p>Sé la primera persona en iniciar la conversación.</p>
                                    </div>
                                @endforelse
                            </div>

                            <footer class="community-chat-composer">
                                <x-access-gate
                                    section="community"
                                    :title="$communityGateTitle"
                                    preview="Puedes leer el chat; enviar mensajes requiere Royal Pass."
                                    :cta="$communityGateCta"
                                    :href="$communityGateHref"
                                >
                                    <form data-community-live-chat-form data-endpoint="{{ $liveChat['message_endpoint'] ?? '' }}">
                                        <label class="sr-only" for="liveChatMessage">Mensaje de chat</label>
                                        <input id="liveChatMessage" name="body" type="text" maxlength="300" placeholder="Escribe un mensaje...">
                                        <button type="submit" aria-label="Enviar mensaje">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path></svg>
                                        </button>
                                        <p class="community-form-status" data-form-status></p>
                                    </form>
                                </x-access-gate>
                                <p>Sé amable · El spam y el abuso se moderan</p>
                            </footer>
                        </section>
                    </section>
                </div>

                <x-public-navigation active="royals" mobile />
            </main>
        </div>

        <div class="community-toast" id="communityToast" role="status" aria-live="polite"></div>
        @include('partials.music-player-modal')
    </body>
</html>
