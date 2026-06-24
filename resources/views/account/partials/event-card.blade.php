<article class="account-event-card">
    <img
        class="account-event-image"
        src="{{ $eventCard['image_url'] }}"
        alt="{{ $eventCard['image_alt'] }}"
        loading="lazy"
        decoding="async"
    >
    <div class="account-event-copy">
        <span class="account-event-badge {{ $eventCard['badge_class'] }}">{{ $eventCard['badge'] }}</span>
        <h3>{{ $eventCard['title'] }}</h3>
        <p>{{ $eventCard['meta'] }}</p>

        @if (! empty($eventCard['cta_url']) && empty($eventCard['cta_disabled']))
            <a class="store-button account-event-cta" href="{{ $eventCard['cta_url'] }}">{{ $eventCard['cta_label'] }}</a>
        @else
            <button class="store-button account-event-cta" type="button" disabled>{{ $eventCard['cta_label'] }}</button>
        @endif
    </div>
</article>
