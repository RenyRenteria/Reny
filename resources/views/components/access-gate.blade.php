@props([
    'section' => 'royal',
    'title' => 'Royal Pass required',
    'preview' => 'Preview available in Open mode.',
    'cta' => 'Get your Royal Pass',
])

@php
    $canAccess = \App\Support\EntitlementMatrix::canUseRoyalFeature(auth()->user());
@endphp

@if ($canAccess)
    {{ $slot }}
@else
    <div class="access-gate" data-section="{{ $section }}">
        <div>
            <span>{{ strtoupper($section) }} PREVIEW</span>
            <strong>{{ $title }}</strong>
            <p>{{ $preview }}</p>
        </div>
        <a class="access-gate-button" href="{{ route('store') }}">{{ $cta }}</a>
    </div>
@endif
