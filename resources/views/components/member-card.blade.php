@php
    $isGuestPreview = (bool) request()->attributes->get('site_editor_guest_preview', false);
    $user = $isGuestPreview ? null : auth()->user();
    $state = \App\Support\AccountStateView::for($user);
@endphp

<div class="member-card" data-access-state="{{ $state['state'] }}">
    <div class="member-avatar" aria-hidden="true"></div>
    <div>
        <strong>{{ $user?->name ?? 'Guest' }}</strong>
        <span id="tierLabel">{{ $state['member_label'] }}</span>
        @if ($user === null)
            <a class="member-card-link" href="{{ route('login') }}">Sign in</a>
        @else
            <a class="member-card-link" href="{{ route('account.show') }}">Account</a>
        @endif
    </div>
</div>
