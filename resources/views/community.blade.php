@php
    $directPosts = [
        [
            'title' => 'Studio note from Reny',
            'date' => 'Today',
            'body' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first.',
            'image' => 'studio.jpg',
            'reactions' => 284,
            'replies' => 38,
        ],
        [
            'title' => 'Capri photo drop',
            'date' => 'This week',
            'body' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next.',
            'image' => 'capri.jpg',
            'reactions' => 319,
            'replies' => 51,
        ],
    ];

    $polls = [
        [
            'question' => 'Which drop should go first?',
            'total' => 1248,
            'options' => [
                ['label' => 'Studio photos', 'percent' => 42],
                ['label' => 'Performance stills', 'percent' => 34],
                ['label' => 'Travel archive', 'percent' => 24],
            ],
        ],
        [
            'question' => 'Next community live topic?',
            'total' => 936,
            'options' => [
                ['label' => 'Photo Q&A', 'percent' => 36],
                ['label' => 'Songwriting stories', 'percent' => 33],
                ['label' => 'Country meetups', 'percent' => 31],
            ],
        ],
    ];

    $groups = [
        [
            'country' => 'Dominican Republic',
            'members' => '8.4K',
            'activity' => 'Planning Santo Domingo listening party',
            'messages' => [
                ['author' => 'Mia', 'text' => 'Who is going to the first meetup?'],
                ['author' => 'Luis', 'text' => 'We should pin a date after the next Reny post.'],
            ],
        ],
        [
            'country' => 'Panama',
            'members' => '6.9K',
            'activity' => 'Sharing radio and TV clips',
            'messages' => [
                ['author' => 'Ana', 'text' => 'The Mas23 visit belongs in the archive.'],
                ['author' => 'Rafa', 'text' => 'I can help organize the Panama photo thread.'],
            ],
        ],
        [
            'country' => 'Colombia',
            'members' => '4.2K',
            'activity' => 'Building the Bogota fan map',
            'messages' => [
                ['author' => 'Valen', 'text' => 'Bogota group is ready for a watch party.'],
                ['author' => 'Nico', 'text' => 'Let us split cities into subthreads later.'],
            ],
        ],
        [
            'country' => 'Mexico',
            'members' => '5.1K',
            'activity' => 'Collecting playlist ideas',
            'messages' => [
                ['author' => 'Sofi', 'text' => 'Mexico City needs its own drop night.'],
                ['author' => 'Diego', 'text' => 'I will invite more fans from the dance group.'],
            ],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Community | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
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
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab is-active" href="{{ url('/community') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab" href="#store">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>STORE</span>
                        </a>
                    </nav>
                </div>

                <div class="member-card">
                    <div class="member-avatar" aria-hidden="true"></div>
                    <div>
                        <strong>Alex Carter</strong>
                        <span>VIP MEMBER</span>
                    </div>
                </div>
            </aside>

            <main class="main-content community-content" id="community">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <section class="community-section direct-posts" aria-labelledby="direct-posts-title">
                    <div class="community-section-head">
                        <div>
                            <span>Official feed</span>
                            <h1 id="direct-posts-title">Reny Direct Posts</h1>
                        </div>
                        <button class="view-all community-toast-trigger" type="button" data-toast="Archive coming soon">VIEW ALL</button>
                    </div>

                    <div class="direct-post-grid">
                        @foreach ($directPosts as $post)
                            <article class="direct-post-card">
                                <img
                                    src="{{ asset('images/photos/' . $post['image']) }}"
                                    alt="{{ $post['title'] }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                                <div class="direct-post-copy">
                                    <span>{{ $post['date'] }}</span>
                                    <h2>{{ $post['title'] }}</h2>
                                    <p>{{ $post['body'] }}</p>
                                    <div class="post-actions" aria-label="Post actions">
                                        <button class="reaction-button" type="button" data-count="{{ $post['reactions'] }}">
                                            <span class="reaction-count">{{ $post['reactions'] }}</span> likes
                                        </button>
                                        <button class="reply-count-button community-toast-trigger" type="button" data-toast="Thread view coming soon">
                                            {{ $post['replies'] }} replies
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="community-section polls-section" aria-labelledby="polls-title">
                    <div class="community-section-head">
                        <div>
                            <span>Fan votes</span>
                            <h2 id="polls-title">Polls</h2>
                        </div>
                        <button class="view-all community-toast-trigger" type="button" data-toast="Poll archive coming soon">VIEW ALL</button>
                    </div>

                    <div class="poll-grid">
                        @foreach ($polls as $poll)
                            <article class="poll-card" data-poll>
                                <div class="poll-card-head">
                                    <h3>{{ $poll['question'] }}</h3>
                                    <span>{{ $poll['total'] }} votes</span>
                                </div>
                                <div class="poll-options">
                                    @foreach ($poll['options'] as $option)
                                        <button
                                            class="poll-option"
                                            type="button"
                                            data-percent="{{ $option['percent'] }}"
                                        >
                                            <span class="poll-option-top">
                                                <span>{{ $option['label'] }}</span>
                                                <strong>{{ $option['percent'] }}%</strong>
                                            </span>
                                            <span class="poll-meter" aria-hidden="true">
                                                <span style="width: {{ $option['percent'] }}%"></span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="community-section country-groups-section" aria-labelledby="country-groups-title">
                    <div class="community-section-head">
                        <div>
                            <span>User-created communities</span>
                            <h2 id="country-groups-title">Country Groups</h2>
                        </div>
                        <button class="view-all community-toast-trigger" type="button" data-toast="Country directory coming soon">VIEW ALL COUNTRIES</button>
                    </div>

                    <div class="country-groups-layout">
                        <div class="country-groups-list" role="tablist" aria-label="Country groups">
                            @foreach ($groups as $group)
                                <button
                                    class="country-group-tab{{ $loop->first ? ' is-active' : '' }}"
                                    id="country-tab-{{ $loop->index }}"
                                    type="button"
                                    role="tab"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="country-panel"
                                    tabindex="{{ $loop->first ? '0' : '-1' }}"
                                    data-country="{{ $group['country'] }}"
                                    data-members="{{ $group['members'] }}"
                                    data-activity="{{ $group['activity'] }}"
                                    data-messages='@json($group['messages'])'
                                >
                                    <strong>{{ $group['country'] }}</strong>
                                    <span>{{ $group['members'] }} members</span>
                                </button>
                            @endforeach

                            <button class="create-group-card" id="openCreateGroup" type="button">
                                <span>+</span>
                                Create custom country group
                            </button>
                        </div>

                        <article class="country-chat-panel" id="country-panel" role="tabpanel" aria-live="polite">
                            <div class="country-chat-head">
                                <div>
                                    <span id="countryMembers">{{ $groups[0]['members'] }} members</span>
                                    <h3 id="countryName">{{ $groups[0]['country'] }}</h3>
                                    <p id="countryActivity">{{ $groups[0]['activity'] }}</p>
                                </div>
                            </div>

                            <div class="country-chat-feed" id="countryChatFeed">
                                @foreach ($groups[0]['messages'] as $message)
                                    <article class="chat-message">
                                        <strong>{{ $message['author'] }}</strong>
                                        <p>{{ $message['text'] }}</p>
                                    </article>
                                @endforeach
                            </div>

                            <form class="country-reply-form" id="countryReplyForm">
                                <label class="sr-only" for="countryReplyInput">Reply to country group</label>
                                <input id="countryReplyInput" type="text" placeholder="Reply to this country group" autocomplete="off">
                                <button type="submit">Send</button>
                            </form>
                        </article>
                    </div>
                </section>

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
                    <a href="#store">
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

        <div class="create-group-modal" id="createGroupModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createGroupTitle">
            <div class="create-group-dialog">
                <div class="create-group-head">
                    <div>
                        <span>Country group</span>
                        <h2 id="createGroupTitle">Create group</h2>
                    </div>
                    <button class="create-group-close" id="closeCreateGroup" type="button" aria-label="Close create group">Close</button>
                </div>

                <form class="create-group-form" id="createGroupForm">
                    <label for="createCountryName">Country</label>
                    <input id="createCountryName" name="country" type="text" placeholder="Puerto Rico" autocomplete="off" required>

                    <label for="createCountryActivity">Group topic</label>
                    <input id="createCountryActivity" name="activity" type="text" placeholder="Planning the next listening party" autocomplete="off" required>

                    <button type="submit">Create group</button>
                </form>
            </div>
        </div>

        <div class="community-toast" id="communityToast" role="status" aria-live="polite"></div>
    </body>
</html>
