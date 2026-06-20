@php
    $slotKeys = ['event_primary', 'event_secondary', 'album', 'merch'];
    $slotLabels = [
        'event_primary' => 'Event 1',
        'event_secondary' => 'Event 2',
        'album' => 'Album',
        'merch' => 'Crown Collection',
    ];
    $editorPage = $editorPage ?? 'store';
    $isHomeEditor = $editorPage === 'home';
    $editorTitle = $isHomeEditor ? 'Home' : 'Store';
    $editorDescription = $isHomeEditor
        ? 'Home / controla Royal Pass, eventos y album deluxe usados en la portada publica.'
        : 'Store / controla los 2 eventos, album deluxe y Crown Collection del website publico.';
    $slotField = fn (string $slot, string $key): string => (string) old("slots.{$slot}.{$key}", data_get($storefront, "slots.{$slot}.{$key}", ''));
    $slotDateField = function (string $slot, string $key) use ($slotField): string {
        $value = $slotField($slot, $key);

        if ($value === '') {
            return '';
        }

        try {
            return \Carbon\CarbonImmutable::parse($value, config('admin.publishing_timezone', config('app.timezone')))->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return $value;
        }
    };
    $royalField = fn (string $key): string => (string) old("royal_pass.{$key}", data_get($storefront, "royal_pass.{$key}", ''));
    $status = old('status', $storefront['_editor_status'] ?? 'draft');
    $publishedAt = $storefront['_published_at'] ?? null;
    $updatedAt = $storefront['_updated_at'] ?? null;
@endphp

<div class="music-banner-cms store-cms-editor">
    <div class="admin-page-heading music-banner-heading">
        <div>
            <h1>{{ $editorTitle }}</h1>
            <p>{{ $editorDescription }}</p>
        </div>
        <div class="admin-actions">
            <span @class([
                'admin-status-pill',
                'admin-status-success' => $status === 'published',
                'admin-status-warning' => $status !== 'published',
            ])>
                {{ $status === 'published' ? 'Publicado' : 'Borrador' }}
            </span>
            <a class="admin-button admin-button-ghost" href="{{ $publicUrl }}" target="_blank" rel="noreferrer">Ver website</a>
        </div>
    </div>

    <form class="music-banner-form store-cms-form" action="{{ route('admin.site-editor.storefront.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="return_page" value="{{ $editorPage }}">

        <section class="music-banner-editor admin-panel" aria-label="Royal Pass banner">
            <div class="music-banner-panel-head">
                <div>
                    <p class="admin-kicker">Open view</p>
                    <h2>Royal Pass banner</h2>
                </div>
                <span>{{ $updatedAt ? 'Editado '.$updatedAt->diffForHumans() : 'Sin cambios guardados' }}</span>
            </div>

            <div class="music-banner-form-grid two">
                <label>
                    <span>Texto antes</span>
                    <input name="royal_pass[copy_before]" value="{{ $royalField('copy_before') }}">
                </label>
                <label>
                    <span>Texto destacado</span>
                    <input name="royal_pass[emphasis]" value="{{ $royalField('emphasis') }}">
                </label>
            </div>

            <label>
                <span>Texto despues</span>
                <input name="royal_pass[copy_after]" value="{{ $royalField('copy_after') }}">
            </label>

            <div class="music-banner-form-grid two">
                <label>
                    <span>CTA</span>
                    <input name="royal_pass[cta_label]" value="{{ $royalField('cta_label') }}">
                </label>
                <label>
                    <span>Product key</span>
                    <input name="royal_pass[product_key]" value="{{ $royalField('product_key') }}">
                </label>
            </div>
        </section>

        <div class="store-cms-slot-grid">
            @foreach ($slotKeys as $slot)
                @php
                    $slotImage = data_get($storefront, "slots.{$slot}.image_url") ?: asset(data_get($storefront, "slots.{$slot}.image", 'images/store/work-in-progress.png'));
                    $slotAction = $slotField($slot, 'action_type');
                    $slotImageAssetId = (string) old("slots.{$slot}.image_asset_id", data_get($storefront, "slots.{$slot}.image_asset_id", ''));
                    $slotContentId = (string) old("slots.{$slot}.content_id", data_get($storefront, "slots.{$slot}.content_id", ''));
                @endphp

                <section class="music-banner-editor admin-panel store-cms-slot" aria-label="{{ $slotLabels[$slot] }}">
                    <div class="music-banner-panel-head">
                        <div>
                            <p class="admin-kicker">Slot</p>
                            <h2>{{ $slotLabels[$slot] }}</h2>
                        </div>
                    </div>

                    <img class="store-cms-thumb" src="{{ $slotImage }}" alt="">

                    @if ($slot === 'album')
                        <label>
                            <span>Album a mostrar</span>
                            <select name="slots[{{ $slot }}][content_id]">
                                <option value="">Default / manual</option>
                                @foreach ($albums as $album)
                                    <option value="{{ $album->id }}" @selected($slotContentId === (string) $album->id)>
                                        {{ $album->title }} · {{ str_replace('_', ' ', $album->status->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label>
                        <span>Titulo</span>
                        <input name="slots[{{ $slot }}][title]" value="{{ $slotField($slot, 'title') }}">
                    </label>

                    <label>
                        <span>Etiqueta</span>
                        <input name="slots[{{ $slot }}][eyebrow]" value="{{ $slotField($slot, 'eyebrow') }}">
                    </label>

                    <label>
                        <span>Descripcion</span>
                        <textarea name="slots[{{ $slot }}][description]" rows="3">{{ $slotField($slot, 'description') }}</textarea>
                    </label>

                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Precio visible</span>
                            <input name="slots[{{ $slot }}][price_label]" value="{{ $slotField($slot, 'price_label') }}">
                        </label>
                        <label>
                            <span>CTA</span>
                            <input name="slots[{{ $slot }}][cta_label]" value="{{ $slotField($slot, 'cta_label') }}">
                        </label>
                    </div>

                    @if (str_starts_with($slot, 'event_'))
                        <label>
                            <span>Countdown fecha/hora</span>
                            <input name="slots[{{ $slot }}][countdown_at]" type="datetime-local" value="{{ $slotDateField($slot, 'countdown_at') }}">
                        </label>
                    @endif

                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Accion</span>
                            <select name="slots[{{ $slot }}][action_type]">
                                <option value="buy" @selected($slotAction === 'buy')>Checkout</option>
                                <option value="rsvp" @selected($slotAction === 'rsvp')>RSVP</option>
                                <option value="link" @selected($slotAction === 'link')>Link externo</option>
                            </select>
                        </label>
                        <label>
                            <span>Product / event key</span>
                            <input name="slots[{{ $slot }}][product_key]" value="{{ $slotField($slot, 'product_key') }}">
                        </label>
                    </div>

                    <label>
                        <span>URL externa</span>
                        <input name="slots[{{ $slot }}][url]" type="url" value="{{ $slotField($slot, 'url') }}">
                    </label>

                    <div class="music-banner-form-grid two">
                        <label>
                            <span>Imagen existente</span>
                            @include('admin.partials.asset-select', [
                                'name' => "slots[{$slot}][image_asset_id]",
                                'selected' => $slotImageAssetId,
                                'mediaAssets' => $mediaAssets,
                                'types' => ['image', 'thumbnail'],
                            ])
                        </label>
                        <label>
                            <span>Subir imagen</span>
                            <input name="slot_images[{{ $slot }}]" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                        </label>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="music-banner-actions store-cms-actions">
            <button class="admin-button admin-button-primary" type="submit" name="action" value="publish">Guardar y publicar</button>
            <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
            <span>{{ $publishedAt ? 'Publicado '.$publishedAt->timezone($timezone ?? config('admin.publishing_timezone'))->format('M d, Y g:i A') : 'Sin version publicada' }}</span>
        </div>
    </form>
</div>
