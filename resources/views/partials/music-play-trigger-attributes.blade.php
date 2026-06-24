@php
    $itemType = $type ?? ($item['kind'] ?? 'music');
    $title = $item['title'] ?? 'music item';
    $label = $label ?? "Play {$title}";
@endphp

data-music-play
data-play-url="{{ $item['play_url'] ?? '' }}"
data-detail-url="{{ $item['detail_url'] ?? ($item['url'] ?? '') }}"
data-access-state="{{ $item['access_state'] ?? 'playback_error' }}"
data-access-label="{{ $item['access_label'] ?? 'Unavailable' }}"
data-access-message="{{ $item['access_message'] ?? 'This music item is not connected to playback yet.' }}"
data-cta-label="{{ $item['cta_label'] ?? '' }}"
data-cta-url="{{ $item['cta_url'] ?? '' }}"
data-image-url="{{ $item['image_url'] ?? '' }}"
data-analytics-id="{{ $item['id'] ?? \Illuminate\Support\Str::slug($title) }}"
data-analytics-label="{{ $title }}"
data-analytics-type="{{ $itemType }}"
aria-label="{{ $label }}"
