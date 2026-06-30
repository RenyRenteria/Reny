@php
    $size = preg_replace('/[^a-z0-9_-]/i', '', (string) ($photo['size'] ?? 'standard')) ?: 'standard';
    $title = (string) ($photo['title'] ?? 'Photo');
    $type = (string) ($photo['type'] ?? 'Photo');
    $tone = (string) ($photo['tone'] ?? 'gallery');
    $caption = (string) ($photo['caption'] ?? '');
    $photoSrc = $photo['image_url'] ?? asset('images/photos/' . ($photo['image'] ?? 'cover.jpg'));
    $isLocked = ! empty($photo['locked']);
@endphp

<button
    class="photo-tile is-{{ $size }}"
    type="button"
    data-photo-title="{{ $title }}"
    data-photo-type="{{ $type }}"
    data-photo-tone="{{ $tone }}"
    data-photo-caption="{{ $caption }}"
    data-photo-src="{{ $photoSrc }}"
    data-photo-id="{{ $photo['id'] ?? '' }}"
    data-photo-album-id="{{ $photo['album_id'] ?? '' }}"
    data-photo-locked="{{ $isLocked ? 'true' : 'false' }}"
>
    <img
        src="{{ $photoSrc }}"
        alt="{{ $title }}"
        loading="lazy"
        decoding="async"
    >
    @if ($isLocked)
        <span class="photo-royal-crown" data-photo-royal-crown aria-hidden="true">
            <svg viewBox="0 0 48 48" focusable="false">
                <path d="M9.5 18.4 17 23l7-12.5L31 23l7.5-4.6-4.1 17.1c-.4 1.5-1.7 2.5-3.2 2.5H16.8c-1.5 0-2.8-1-3.2-2.5L9.5 18.4Zm8 14.6h13l1.4-5.9-3.6 2.2-4.3-7.7-4.3 7.7-3.6-2.2 1.4 5.9Z"></path>
            </svg>
        </span>
    @endif
    <span class="photo-overlay" aria-hidden="true">
        <span>{{ $type }} / {{ $tone }}</span>
        <strong>{{ $title }}</strong>
    </span>
</button>
