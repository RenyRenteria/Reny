<nav class="site-media-nav" aria-label="Música y archivo visual">
    <a href="{{ route('music') }}" @if (request()->routeIs('music*')) aria-current="page" @endif>Music</a>
    <a href="{{ route('videos') }}" @if (request()->routeIs('videos')) aria-current="page" @endif>Videos</a>
    <a href="{{ route('photos') }}" @if (request()->routeIs('photos')) aria-current="page" @endif>Photos</a>
</nav>
