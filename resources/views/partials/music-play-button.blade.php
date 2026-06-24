@php
    $buttonClass = $class ?? 'mini-play';
    $title = $item['title'] ?? 'music item';
@endphp

<button
    class="{{ $buttonClass }}"
    type="button"
    @include('partials.music-play-trigger-attributes', ['item' => $item, 'type' => $type ?? null, 'label' => "Play {$title}"])
>
    <span></span>
</button>
