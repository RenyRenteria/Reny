@php
    $community = $community ?? [];
    $communityPosts = $community['posts'] ?? [];
    $communityPoll = $community['poll'] ?? null;
    $communityClubs = $community['clubs'] ?? [];
    $activeClub = $community['active_club'] ?? ($communityClubs[0] ?? null);
    $canUseCommunityActions = (bool) ($community['can_use_actions'] ?? false);
    $communityGateHref = auth()->check() ? route('store') : route('login');
    $communityGateCta = auth()->check() ? 'Get your Royal Pass' : 'Sign in';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Community | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="community">
        <div class="community-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ url('/') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>Music</span>
                        </a>
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>Videos</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>Photos</span>
                        </a>
                        <a class="tab is-active" href="{{ url('/community') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>Community</span>
                        </a>
                        <a class="tab" href="{{ url('/store') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>Store</span>
                        </a>
                    </nav>
                </div>

                <x-member-card />
            </aside>

            <main class="main-content community-content" id="community">
                <header class="mobile-header community-mobile-header">
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>
                    <div class="direct-posts">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3l2.7 5.4 6 .9-4.4 4.2 1 6-5.3-2.8-5.3 2.8 1-6-4.4-4.2 6-.9L12 3Z"></path>
                            </svg>
                        </span>
                        Reny Direct Posts
                    </div>
                </header>

                <div class="community-grid">
                    <section class="feed-column" aria-label="Official community feed">
                        <div class="feed-heading">
                            <div>
                                <p class="community-eyebrow">Fan-safe &amp; moderated</p>
                                <h1>Official Feed</h1>
                            </div>
                            <div class="direct-posts">
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 3l2.7 5.4 6 .9-4.4 4.2 1 6-5.3-2.8-5.3 2.8 1-6-4.4-4.2 6-.9L12 3Z"></path>
                                    </svg>
                                </span>
                                Reny Direct Posts
                            </div>
                        </div>

                        @foreach ($communityPosts as $post)
                            <article class="post-card" id="{{ $post['key'] }}" data-title="{{ $post['title'] }}">
                                <div class="post-head">
                                    <div class="post-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 2v20"></path>
                                            <path d="M8 7v5a4 4 0 0 0 8 0V7"></path>
                                            <path d="M6 12a6 6 0 0 0 12 0"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2>{{ $post['title'] }}</h2>
                                        <div class="post-time">{{ $post['time'] }}</div>
                                    </div>
                                </div>

                                <p class="post-copy">{{ $post['body'] }}</p>

                                @if (! empty($post['image_url']))
                                    <div class="media-frame">
                                        <img src="{{ $post['image_url'] }}" alt="{{ $post['image_alt'] }}">
                                        @if (! empty($post['url']))
                                            <a
                                                class="media-cta"
                                                href="{{ $post['url'] }}"
                                                data-analytics-id="{{ $post['key'] }}"
                                                data-analytics-type="reny_note"
                                            >
                                                {{ $post['cta'] }}
                                                <span aria-hidden="true">-&gt;</span>
                                            </a>
                                        @else
                                            <button
                                                class="media-cta"
                                                type="button"
                                                data-note-open
                                                data-note-title="{{ $post['title'] }}"
                                                data-note-body="{{ $post['full_body'] }}"
                                                data-analytics-id="{{ $post['key'] }}"
                                                data-analytics-type="reny_note"
                                            >
                                                {{ $post['cta'] }}
                                                <span aria-hidden="true">-&gt;</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                <div class="post-actions">
                                    <div class="post-metrics">
                                        @if ($canUseCommunityActions)
                                            <button
                                                class="metric heart reaction-button @if ($post['liked']) is-reacted @endif"
                                                type="button"
                                                data-community-like
                                                data-endpoint="{{ $post['like_endpoint'] }}"
                                                data-count="{{ $post['like_count'] }}"
                                                data-analytics-id="{{ $post['key'] }}"
                                                data-analytics-type="reaction"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path>
                                                </svg>
                                                <span class="reaction-count">{{ $post['like_count'] }}</span>
                                            </button>
                                        @else
                                            <span class="metric heart">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path>
                                                </svg>
                                                {{ $post['like_count'] }}
                                            </span>
                                        @endif

                                        <span class="metric" data-reply-count="{{ $post['key'] }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                                            </svg>
                                            <span>{{ $post['reply_count'] }} replies</span>
                                        </span>
                                    </div>
                                    <button
                                        class="share"
                                        type="button"
                                        data-share-url="{{ $post['share_url'] }}"
                                        data-share-title="{{ $post['title'] }}"
                                        data-analytics-id="{{ $post['key'] }}"
                                        data-analytics-type="post"
                                        aria-label="Share {{ $post['title'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <path d="m8.6 13.5 6.8 4"></path>
                                            <path d="m15.4 6.5-6.8 4"></path>
                                        </svg>
                                    </button>
                                </div>

                                <x-access-gate
                                    section="community"
                                    title="Sign in to reply"
                                    preview="Replies stay visible as previews; posting requires Royal Pass."
                                    :cta="$communityGateCta"
                                    :href="$communityGateHref"
                                >
                                    <form
                                        class="country-reply-form community-reply-form"
                                        data-community-reply-form
                                        data-endpoint="{{ $post['reply_endpoint'] }}"
                                        data-post-key="{{ $post['key'] }}"
                                    >
                                        <label class="sr-only" for="reply-{{ $post['key'] }}">Reply to {{ $post['title'] }}</label>
                                        <input id="reply-{{ $post['key'] }}" name="body" type="text" placeholder="Write a kind reply...">
                                        <button type="submit">Reply</button>
                                        <p class="community-form-status" data-form-status></p>
                                    </form>
                                </x-access-gate>
                            </article>
                        @endforeach

                        @if ($communityPoll)
                            <section
                                class="vote-card"
                                aria-labelledby="fan-votes-title"
                                data-community-poll
                                data-poll-key="{{ $communityPoll['key'] }}"
                                data-vote-endpoint="{{ $communityPoll['vote_endpoint'] }}"
                                data-voted="{{ $communityPoll['user_vote'] ? 'true' : 'false' }}"
                            >
                                <div class="section-kicker">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                        <path d="M8 9h8"></path>
                                        <path d="M8 13h5"></path>
                                        <path d="M8 17h8"></path>
                                    </svg>
                                    <span id="fan-votes-title">Fan Votes</span>
                                </div>

                                <h3>{{ $communityPoll['question'] }}</h3>

                                <div class="poll-options">
                                    @foreach ($communityPoll['options'] as $option)
                                        @if ($canUseCommunityActions)
                                            <button
                                                class="poll-option @if ($option['selected']) is-voted @endif"
                                                type="button"
                                                data-option-key="{{ $option['key'] }}"
                                                data-option-label="{{ $option['label'] }}"
                                                data-percent="{{ $option['percent'] }}"
                                                @disabled((bool) $communityPoll['user_vote'])
                                            >
                                                <span class="poll-option-top">
                                                    <span>{{ $option['label'] }}</span>
                                                    <strong>{{ $option['percent'] }}%</strong>
                                                </span>
                                                <span class="poll-meter"><span style="width: {{ $option['percent'] }}%"></span></span>
                                            </button>
                                        @else
                                            <div class="poll-option" data-percent="{{ $option['percent'] }}">
                                                <span class="poll-option-top">
                                                    <span>{{ $option['label'] }}</span>
                                                    <strong>{{ $option['percent'] }}%</strong>
                                                </span>
                                                <span class="poll-meter"><span style="width: {{ $option['percent'] }}%"></span></span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                <div class="vote-footer">
                                    <span data-poll-total>{{ $communityPoll['total_votes_label'] }}</span>
                                    @if ($communityPoll['user_vote'])
                                        <span class="reply-chip">Vote saved</span>
                                    @elseif (! $canUseCommunityActions)
                                        <x-access-gate
                                            section="community"
                                            title="Sign in to vote"
                                            preview="Poll results stay visible in Open mode."
                                            :cta="$communityGateCta"
                                            :href="$communityGateHref"
                                        />
                                    @else
                                        <span class="reply-chip">Choose one option</span>
                                    @endif
                                </div>
                            </section>
                        @endif
                    </section>

                    <aside class="side-column" aria-label="Community sidebar">
                        <div class="side-head">
                            <h2>Country Clubs</h2>
                            <a href="#clubs">View all</a>
                        </div>

                        <div class="club-list" id="clubs">
                            @foreach ($communityClubs as $club)
                                <article class="club-card" data-club-key="{{ $club['key'] }}">
                                    <div class="flag" aria-hidden="true">{{ $club['flag_label'] }}</div>
                                    <div>
                                        <strong><a href="{{ $club['detail_url'] }}">{{ $club['name'] }}</a></strong>
                                        <span>{{ $club['members_label'] }} · {{ $club['activity'] }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($activeClub)
                            <x-access-gate
                                section="community"
                                title="Sign in to join"
                                preview="Club previews stay public; creating or joining requires Royal Pass."
                                :cta="$communityGateCta"
                                :href="$communityGateHref"
                            >
                                <div class="club-actions">
                                    <button class="outline-button" id="openCreateGroup" type="button">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5v14"></path>
                                            <path d="M5 12h14"></path>
                                        </svg>
                                        Create group
                                    </button>
                                    <button
                                        class="join-button"
                                        type="button"
                                        data-community-club-join
                                        data-club-key="{{ $activeClub['key'] }}"
                                        data-endpoint="{{ $activeClub['join_endpoint'] }}"
                                        data-joined="{{ $activeClub['joined'] ? 'true' : 'false' }}"
                                    >
                                        {{ $activeClub['joined'] ? 'Joined' : 'Join group' }}
                                    </button>
                                </div>
                            </x-access-gate>

                            <div class="chat-wrap">
                                <x-access-gate
                                    section="community"
                                    title="Clubhouse Chat"
                                    preview="Chat previews are visible in Open mode; sending messages requires Royal Pass."
                                    :cta="$communityGateCta"
                                    :href="$communityGateHref"
                                >
                                    <section class="chat-card" aria-labelledby="chat-title">
                                        <div class="chat-head">
                                            <div>
                                                <h2 id="chat-title">Clubhouse Chat</h2>
                                                <span class="reply-chip">{{ $activeClub['name'] }}</span>
                                            </div>
                                            <span class="status-dot" aria-label="Live chat"></span>
                                        </div>

                                        <div class="country-chat-feed" id="countryChatFeed">
                                            @foreach ($activeClub['messages'] as $message)
                                                <article class="chat-message">
                                                    <strong>{{ $message['author'] }}</strong>
                                                    <p>{{ $message['text'] }}</p>
                                                </article>
                                            @endforeach
                                        </div>

                                        <form
                                            class="country-reply-form"
                                            id="countryReplyForm"
                                            data-community-club-message
                                            data-endpoint="{{ $activeClub['message_endpoint'] }}"
                                            data-club-key="{{ $activeClub['key'] }}"
                                        >
                                            <label class="sr-only" for="message">Type a message</label>
                                            <input id="message" name="body" type="text" placeholder="Type a message...">
                                            <button type="submit">Send</button>
                                            <p class="community-form-status" data-form-status></p>
                                        </form>
                                    </section>
                                </x-access-gate>
                            </div>
                        @endif
                    </aside>
                </div>

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ url('/') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="{{ url('/videos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="m22 8-6 4 6 4V8Z"></path>
                            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                        </svg>
                        <span class="sr-only">VIDEOS</span>
                    </a>
                    <a href="{{ url('/photos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                        <span class="sr-only">PHOTOS</span>
                    </a>
                    <a class="is-active" href="{{ url('/community') }}" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                        </svg>
                        <span class="sr-only">COMMUNITY</span>
                    </a>
                    <a href="{{ url('/store') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 10h16"></path>
                            <path d="M5 10l1.5-5h11L19 10"></path>
                            <path d="M6 10v9h12v-9"></path>
                            <path d="M9 19v-5h6v5"></path>
                        </svg>
                        <span class="sr-only">STORE</span>
                    </a>
                </nav>
            </main>
        </div>

        <div class="create-group-modal" id="communityNoteModal" aria-hidden="true">
            <div class="create-group-dialog" role="dialog" aria-modal="true" aria-labelledby="communityNoteTitle">
                <div class="create-group-head">
                    <div>
                        <span>Reny note</span>
                        <h2 id="communityNoteTitle">Reny note</h2>
                    </div>
                    <button class="create-group-close" id="closeCommunityNote" type="button">Close</button>
                </div>
                <div class="community-note-body" id="communityNoteBody"></div>
            </div>
        </div>

        @if ($canUseCommunityActions)
            <div class="create-group-modal" id="createGroupModal" aria-hidden="true">
                <div class="create-group-dialog" role="dialog" aria-modal="true" aria-labelledby="createGroupTitle">
                    <div class="create-group-head">
                        <div>
                            <span>Country club</span>
                            <h2 id="createGroupTitle">Create Country Club</h2>
                        </div>
                        <button class="create-group-close" id="closeCreateGroup" type="button">Close</button>
                    </div>
                    <form
                        class="create-group-form"
                        id="createGroupForm"
                        data-endpoint="{{ route('community.clubs.store') }}"
                    >
                        <label for="createCountryName">Country or city</label>
                        <input id="createCountryName" name="name" type="text" maxlength="80" required>
                        <label for="createCountryActivity">Current activity</label>
                        <input id="createCountryActivity" name="activity" type="text" maxlength="140" required>
                        <button type="submit">Create</button>
                        <p class="community-form-status" data-form-status></p>
                    </form>
                </div>
            </div>
        @endif

        <div class="community-toast" id="communityToast" role="status" aria-live="polite"></div>
    </body>
</html>
