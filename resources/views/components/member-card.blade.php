@php
    $user = auth()->user();
    $stateView = \App\Support\AccessStatePresenter::for($user, request()->path());
@endphp

<div class="member-card" data-access-state="{{ $stateView['state'] }}">
    <div class="member-avatar" aria-hidden="true"></div>
    <div>
        <strong>{{ $user?->name ?? 'Guest' }}</strong>
        <span id="tierLabel">{{ $stateView['sidebar_label'] }}</span>
        @guest
            <a class="member-card-link" href="{{ route('login') }}">Sign in</a>
        @else
            <a class="member-card-link" href="{{ route('account.show') }}">Account</a>
        @endguest
    </div>
</div>
