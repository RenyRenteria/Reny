@php
    $tabs = \App\Support\AdminCmsSections::tabs();
    $activeSection = $activeSection ?? null;
@endphp

<nav class="admin-section-tabs" aria-label="CMS content sections">
    @foreach ($tabs as $section => $tab)
        <a
            @class(['is-active' => $activeSection === $section])
            href="{{ route('admin.content.index', ['section' => $section]) }}"
            style="--section-accent: {{ $tab['accent'] }}"
        >
            <span>{{ $tab['label'] }}</span>
            <strong>{{ $tab['caption'] }}</strong>
        </a>
    @endforeach
</nav>
