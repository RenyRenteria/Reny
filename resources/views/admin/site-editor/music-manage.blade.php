@php
    use App\Enums\ContentType;
    use App\Enums\EditorialStatus;
    use App\Models\EditorialContent;
    use App\Models\MediaAsset;

    $contents = collect($contents ?? []);
    $canDelete = (bool) auth()->user()?->canPublishContent();
    $groups = [
        [
            'key' => 'songs',
            'label' => 'Songs',
            'type' => ContentType::Song,
            'form' => 'admin.site-editor.music-add-song',
        ],
        [
            'key' => 'albums',
            'label' => 'Albums',
            'type' => ContentType::MusicalAlbum,
            'form' => 'admin.site-editor.music-add-album',
        ],
        [
            'key' => 'playlists',
            'label' => 'Playlists',
            'type' => ContentType::MusicPlaylist,
            'form' => 'admin.site-editor.music-add-playlist',
        ],
    ];

    $assetUrl = function (EditorialContent $content): ?string {
        $metadata = $content->metadata ?? [];
        $assetId = match ($content->type) {
            ContentType::Song => data_get($metadata, 'artwork_asset_id'),
            ContentType::MusicalAlbum => data_get($metadata, 'album_artwork_asset_id'),
            ContentType::MusicPlaylist => data_get($metadata, 'playlist_cover_asset_id'),
            default => null,
        };

        $asset = $content->mediaAssets
            ->when($assetId, fn ($assets) => $assets->where('id', (int) $assetId))
            ->first();

        if (! $asset instanceof MediaAsset) {
            $asset = $content->mediaAssets->first();
        }

        return $asset?->publicUrl();
    };

    $metaLine = function (EditorialContent $content): string {
        $metadata = $content->metadata ?? [];
        $status = str($content->status instanceof EditorialStatus ? $content->status->value : (string) $content->status)->replace('_', ' ')->headline();

        if ($content->type === ContentType::MusicPlaylist) {
            return $status.' / '.count(data_get($metadata, 'tracks', [])).' tracks';
        }

        if ($content->type === ContentType::MusicalAlbum) {
            return $status.' / '.count(data_get($metadata, 'tracks', [])).' tracks / Member '.(data_get($metadata, 'release_date_member_view') ?: 'TBD');
        }

        return $status.' / Member '.(data_get($metadata, 'release_date_member_view') ?: 'TBD').' / Open '.(data_get($metadata, 'release_date_open_view') ?: 'TBD');
    };
@endphp

<section class="music-manage admin-panel" aria-label="Manage music content">
    <div class="music-banner-panel-head">
        <div>
            <p class="admin-kicker">CRUD</p>
            <h2>Contenido multimedia</h2>
        </div>
        <span>{{ $contents->count() }} items</span>
    </div>

    <div class="music-manage-groups">
        @foreach ($groups as $group)
            @php
                $items = $contents
                    ->filter(fn (EditorialContent $content): bool => $content->type === $group['type'])
                    ->values();
            @endphp

            <section class="music-manage-group" aria-labelledby="music-manage-{{ $group['key'] }}">
                <div class="music-manage-group-head">
                    <h3 id="music-manage-{{ $group['key'] }}">{{ $group['label'] }}</h3>
                    <span>{{ $items->count() }}</span>
                </div>

                @forelse ($items as $content)
                    @php
                        $thumbnail = $assetUrl($content);
                        $formKey = 'music-'.$group['key'].'-'.$content->id;
                    @endphp

                    <article class="music-manage-item">
                        <details class="music-manage-details">
                            <summary>
                                <span
                                    class="music-manage-thumb"
                                    @if ($thumbnail) style="background-image: url('{{ $thumbnail }}')" @endif
                                    aria-hidden="true"
                                ></span>
                                <span class="music-manage-copy">
                                    <strong>{{ $content->title }}</strong>
                                    <small>{{ $metaLine($content) }}</small>
                                </span>
                                <span class="admin-button admin-button-soft">Editar</span>
                            </summary>

                            <div class="music-manage-edit-panel">
                                @include($group['form'], [
                                    'content' => $content,
                                    'formKey' => $formKey,
                                    'visibilityAudiences' => $visibilityAudiences,
                                    'mediaAssets' => $mediaAssets,
                                    'trackOptions' => $trackOptions,
                                ])
                            </div>
                        </details>

                        @if ($content->status === \App\Enums\EditorialStatus::Draft)
                            <form class="music-manage-delete" method="POST" action="{{ route('admin.content.destroy', $content) }}" onsubmit="return confirm(@js('Eliminar borrador '.$content->title.'?'))">
                                @csrf
                                @method('DELETE')
                                <button class="admin-button admin-button-danger" type="submit" @disabled(! $canDelete)>Eliminar borrador</button>
                            </form>
                        @elseif ($content->status !== \App\Enums\EditorialStatus::Archived)
                            <form class="music-manage-delete" method="POST" action="{{ route('admin.content.archive', $content) }}" onsubmit="return confirm(@js('Archivar '.$content->title.' y retirarlo del website?'))">
                                @csrf
                                <button class="admin-button admin-button-danger" type="submit" @disabled(! $canDelete)>Archivar</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="music-manage-empty">No hay {{ strtolower($group['label']) }} todavia.</div>
                @endforelse
            </section>
        @endforeach
    </div>
</section>
