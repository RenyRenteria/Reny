@php
    $seo = $seo ?? [];
    $fallbackTitle = $fallbackTitle ?? 'Reny Renteria';
    $metaTitle = $seo['meta_title'] ?? $fallbackTitle;
    $metaDescription = $seo['meta_description'] ?? '';
    $canonicalUrl = $seo['canonical_url'] ?? url()->current();
    $ogTitle = $seo['og_title'] ?? $metaTitle;
    $ogDescription = $seo['og_description'] ?? $metaDescription;
    $ogImage = $seo['og_image'] ?? null;
    $twitterTitle = $seo['twitter_title'] ?? $ogTitle;
    $twitterDescription = $seo['twitter_description'] ?? $ogDescription;
    $twitterImage = $seo['twitter_image'] ?? $ogImage;
@endphp

<title>{{ $metaTitle }}</title>
@if (filled($metaDescription))<meta name="description" content="{{ $metaDescription }}">@endif
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $ogTitle }}">
@if (filled($ogDescription))<meta property="og:description" content="{{ $ogDescription }}">@endif
<meta property="og:url" content="{{ $canonicalUrl }}">
@if (filled($ogImage))<meta property="og:image" content="{{ $ogImage }}">@endif
<meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary_large_image' }}">
<meta name="twitter:title" content="{{ $twitterTitle }}">
@if (filled($twitterDescription))<meta name="twitter:description" content="{{ $twitterDescription }}">@endif
@if (filled($twitterImage))<meta name="twitter:image" content="{{ $twitterImage }}">@endif
