@php
    $communityGateHref = auth()->check() ? route('store') : route('login');
    $communityGateCta = auth()->check() ? 'Get your Royal Pass' : 'Sign in';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $club['name'] }} Country Club | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="community_club">
        <div class="community-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ url('/') }}"><span>Music</span></a>
                        <a class="tab" href="{{ url('/videos') }}"><span>Videos</span></a>
                        <a class="tab" href="{{ url('/photos') }}"><span>Photos</span></a>
                        <a class="tab is-active" href="{{ url('/community') }}" aria-current="page"><span>Community</span></a>
                        <a class="tab" href="{{ url('/store') }}"><span>Store</span></a>
                    </nav>
                </div>

                <x-member-card />
            </aside>

            <main class="main-content community-content">
                <section class="community-section community-club-detail" data-club-key="{{ $club['key'] }}">
                    <div class="community-section-head">
                        <div>
                            <span>Country Club</span>
                            <h1>{{ $club['name'] }}</h1>
                        </div>
                        <a class="outline-button community-back-link" href="{{ url('/community') }}">Back to Community</a>
                    </div>

                    <div class="country-chat-panel">
                        <div class="country-chat-head">
                            <span>{{ $club['flag_label'] }} · {{ $club['members_label'] }}</span>
                            <h3>{{ $club['activity'] }}</h3>
                            <p>Join the club to post messages and keep the country thread active.</p>
                        </div>

                        <div class="country-chat-feed" id="countryChatFeed">
                            @foreach ($club['messages'] as $message)
                                <article class="chat-message">
                                    <strong>{{ $message['author'] }}</strong>
                                    <p>{{ $message['text'] }}</p>
                                </article>
                            @endforeach
                        </div>

                        <x-access-gate
                            section="community"
                            title="Sign in to join"
                            preview="Club detail is visible; joining and posting require Royal Pass."
                            :cta="$communityGateCta"
                            :href="$communityGateHref"
                        >
                            <div class="club-detail-actions">
                                <button
                                    class="join-button"
                                    type="button"
                                    data-community-club-join
                                    data-club-key="{{ $club['key'] }}"
                                    data-endpoint="{{ $club['join_endpoint'] }}"
                                    data-joined="{{ $club['joined'] ? 'true' : 'false' }}"
                                >
                                    {{ $club['joined'] ? 'Joined' : 'Join group' }}
                                </button>
                            </div>

                            <form
                                class="country-reply-form"
                                id="countryReplyForm"
                                data-community-club-message
                                data-endpoint="{{ $club['message_endpoint'] }}"
                                data-club-key="{{ $club['key'] }}"
                            >
                                <label class="sr-only" for="message">Type a message</label>
                                <input id="message" name="body" type="text" placeholder="Type a message...">
                                <button type="submit">Send</button>
                                <p class="community-form-status" data-form-status></p>
                            </form>
                        </x-access-gate>
                    </div>
                </section>
            </main>
        </div>

        <div class="community-toast" id="communityToast" role="status" aria-live="polite"></div>
    </body>
</html>
