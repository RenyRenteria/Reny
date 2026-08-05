@if (filled($previewAudience ?? null))
    <aside class="cms-preview-banner" role="status">
        Private CMS preview · {{ ucfirst($previewAudience) }} audience · drafts included
    </aside>
@endif
