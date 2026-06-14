@php
    $user = auth()->user();
    $state = $user?->accessState()->value ?? 'open';
    $label = match ($state) {
        'royal_active', 'royal_grace' => 'ROYAL MEMBER',
        'royal_expired' => 'ROYAL EXPIRED',
        default => 'OPEN ACCESS',
    };
@endphp

<div class="member-card" data-access-state="{{ $state }}">
    <div class="member-avatar" aria-hidden="true"></div>
    <div>
        <strong>{{ $user?->name ?? 'Guest' }}</strong>
        <span id="tierLabel">{{ $label }}</span>
        @guest
            <a class="member-card-link" href="{{ route('login') }}">Sign in</a>
        @else
            <a class="member-card-link" href="{{ route('account.show') }}">Account</a>
        @endguest
    </div>
</div>
