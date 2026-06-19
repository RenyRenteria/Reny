@php
    $field = fn (string $key): string => (string) old($key, $banner[$key] ?? '');
    $status = old('status', $banner['_editor_status'] ?? 'draft');
    $publishedAt = $banner['_published_at'] ?? null;
    $updatedAt = $banner['_updated_at'] ?? null;
@endphp

<div class="music-banner-cms" data-music-banner-editor>
    <div class="admin-page-heading music-banner-heading">
        <div>
            <h1>Banner</h1>
            <p>Music / banner principal del website publico.</p>
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

    <div class="music-banner-workspace">
        <aside class="music-banner-editor admin-panel" aria-label="Formulario para editar banner de musica">
            <div class="music-banner-panel-head">
                <div>
                    <p class="admin-kicker">Banner destacado</p>
                    <h2>Campos editables</h2>
                </div>
                <span>{{ $updatedAt ? 'Editado '.$updatedAt->diffForHumans() : 'Sin cambios guardados' }}</span>
            </div>

            <form class="music-banner-form" action="{{ route('admin.site-editor.music-banner.update') }}" method="POST" enctype="multipart/form-data" data-music-banner-form>
                @csrf

                <input type="hidden" name="image_asset_id" value="{{ old('image_asset_id', $banner['image_asset_id'] ?? '') }}">

                <div class="music-banner-form-grid two">
                    <label>
                        <span>Etiqueta linea 1</span>
                        <input data-banner-input="eyebrow_line_1" name="eyebrow_line_1" value="{{ $field('eyebrow_line_1') }}">
                    </label>
                    <label>
                        <span>Etiqueta linea 2</span>
                        <input data-banner-input="eyebrow_line_2" name="eyebrow_line_2" value="{{ $field('eyebrow_line_2') }}">
                    </label>
                </div>

                <div class="music-banner-form-grid two">
                    <label>
                        <span>Titulo linea 1</span>
                        <input data-banner-input="title_line_1" name="title_line_1" value="{{ $field('title_line_1') }}" required>
                    </label>
                    <label>
                        <span>Titulo linea 2</span>
                        <input data-banner-input="title_line_2" name="title_line_2" value="{{ $field('title_line_2') }}">
                    </label>
                </div>

                <label>
                    <span>Subtitulo</span>
                    <input data-banner-input="subtitle" name="subtitle" value="{{ $field('subtitle') }}">
                </label>

                <label>
                    <span>Descripcion</span>
                    <textarea data-banner-input="description" name="description">{{ $field('description') }}</textarea>
                </label>

                <div class="music-banner-form-grid two">
                    <label>
                        <span>Texto inferior linea 1</span>
                        <input data-banner-input="footer_line_1" name="footer_line_1" value="{{ $field('footer_line_1') }}">
                    </label>
                    <label>
                        <span>Texto inferior linea 2</span>
                        <input data-banner-input="footer_line_2" name="footer_line_2" value="{{ $field('footer_line_2') }}">
                    </label>
                </div>

                <div class="music-banner-form-grid two">
                    <label>
                        <span>Badge</span>
                        <input data-banner-input="badge" name="badge" maxlength="4" value="{{ $field('badge') }}">
                    </label>
                    <label>
                        <span>URL destino</span>
                        <input data-banner-input="destination_url" name="destination_url" type="url" value="{{ $field('destination_url') }}" required>
                    </label>
                </div>

                <div class="music-banner-form-grid two">
                    <label>
                        <span>Sticker linea 1</span>
                        <input data-banner-input="sticker_line_1" name="sticker_line_1" value="{{ $field('sticker_line_1') }}">
                    </label>
                    <label>
                        <span>Sticker linea 2</span>
                        <input data-banner-input="sticker_line_2" name="sticker_line_2" value="{{ $field('sticker_line_2') }}">
                    </label>
                </div>

                <label>
                    <span>Portada / arte</span>
                    <input data-banner-image-input name="image" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                </label>

                <label>
                    <span>Estado</span>
                    <select data-banner-input="status" name="status">
                        <option value="published" @selected($status === 'published')>Publicado</option>
                        <option value="draft" @selected($status !== 'published')>Borrador</option>
                    </select>
                </label>

                <div class="music-banner-actions">
                    <button class="admin-button admin-button-primary" type="submit" name="action" value="publish">Guardar y publicar</button>
                    <button class="admin-button admin-button-soft" type="submit" name="action" value="draft">Guardar borrador</button>
                </div>
            </form>
        </aside>

        <section class="music-banner-preview-shell admin-panel" aria-label="Preview del banner">
            <div class="music-banner-preview-toolbar">
                <div>
                    <p class="admin-kicker">Preview</p>
                    <h2>Banner del website</h2>
                </div>
                <div class="music-banner-device-tabs" aria-label="Vista">
                    <button class="is-active" type="button" data-banner-device="desktop">Desktop</button>
                    <button type="button" data-banner-device="mobile">Mobile</button>
                </div>
            </div>

            <div class="music-banner-stage">
                <article class="music-banner-preview" data-banner-preview>
                    <section @class(['hero', 'is-draft' => $status !== 'published']) aria-label="Featured album" data-banner-hero>
                        <div class="hero-content">
                            <p class="eyebrow">
                                <span data-banner-preview-field="eyebrow_line_1">{{ $field('eyebrow_line_1') }}</span>
                                <br data-banner-break="eyebrow_line_2">
                                <span data-banner-preview-field="eyebrow_line_2">{{ $field('eyebrow_line_2') }}</span>
                            </p>
                            <h1>
                                <span data-banner-preview-field="title_line_1">{{ $field('title_line_1') }}</span>
                                <br data-banner-break="title_line_2">
                                <span data-banner-preview-field="title_line_2">{{ $field('title_line_2') }}</span>
                            </h1>
                            <h2 data-banner-preview-field="subtitle">{{ $field('subtitle') }}</h2>
                            <p class="hero-copy" data-banner-preview-field="description">{{ $field('description') }}</p>
                            <p class="hero-link">
                                <span data-banner-preview-field="footer_line_1">{{ $field('footer_line_1') }}</span>
                                <br data-banner-break="footer_line_2">
                                <span data-banner-preview-field="footer_line_2">{{ $field('footer_line_2') }}</span>
                            </p>
                        </div>

                        <div @class(['artist-card', 'has-uploaded-art' => filled($banner['image_url'] ?? null)]) aria-hidden="true" data-banner-artist-card>
                            <img
                                @class(['artist-card-image', 'is-hidden' => blank($banner['image_url'] ?? null)])
                                src="{{ $banner['image_url'] ?? '' }}"
                                alt=""
                                data-banner-image-preview
                            >
                            <div class="disc-badge" data-banner-preview-field="badge">{{ str($field('badge'))->upper()->limit(4, '') }}</div>
                            <div class="barcode"></div>
                            <div class="artist-sticker">
                                <span data-banner-preview-field="sticker_line_1">{{ $field('sticker_line_1') }}</span>
                                <span data-banner-preview-field="sticker_line_2">{{ $field('sticker_line_2') }}</span>
                            </div>
                        </div>
                    </section>
                </article>
            </div>

            <div class="music-banner-publish-strip">
                <span><strong>Publicacion:</strong> {{ $publishedAt ? $publishedAt->timezone($timezone ?? config('admin.publishing_timezone'))->format('M d, Y g:i A') : 'sin version publicada' }}</span>
                <span>{{ ($banner['_has_draft'] ?? false) ? 'Hay borrador pendiente' : 'Sin borrador pendiente' }}</span>
            </div>
        </section>
    </div>
</div>

<script>
    (() => {
        const root = document.querySelector('[data-music-banner-editor]');

        if (!root) return;

        const inputs = root.querySelectorAll('[data-banner-input]');
        const preview = root.querySelector('[data-banner-preview]');
        const hero = root.querySelector('[data-banner-hero]');
        const artistCard = root.querySelector('[data-banner-artist-card]');
        const imageInput = root.querySelector('[data-banner-image-input]');
        const imagePreview = root.querySelector('[data-banner-image-preview]');

        const setOptionalLine = (key, value) => {
            const line = root.querySelector(`[data-banner-preview-field="${key}"]`);
            const breaker = root.querySelector(`[data-banner-break="${key}"]`);
            const isEmpty = value.trim().length === 0;

            if (line) {
                line.textContent = value;
                line.hidden = isEmpty;
            }

            if (breaker) {
                breaker.hidden = isEmpty;
            }
        };

        const render = () => {
            inputs.forEach((input) => {
                const key = input.dataset.bannerInput;
                const target = root.querySelector(`[data-banner-preview-field="${key}"]`);
                const value = input.value.trim();

                if (['eyebrow_line_2', 'title_line_2', 'footer_line_2'].includes(key)) {
                    setOptionalLine(key, value);
                    return;
                }

                if (!target) return;

                target.textContent = key === 'badge' ? value.slice(0, 4).toUpperCase() : value;
                target.hidden = value.length === 0 && ['badge', 'sticker_line_1', 'sticker_line_2'].includes(key);
            });

            const status = root.querySelector('[data-banner-input="status"]')?.value;
            hero?.classList.toggle('is-draft', status !== 'published');
        };

        inputs.forEach((input) => {
            input.addEventListener('input', render);
            input.addEventListener('change', render);
        });

        imageInput?.addEventListener('change', () => {
            const file = imageInput.files?.[0];

            if (!file || !imagePreview || !artistCard) return;

            imagePreview.src = URL.createObjectURL(file);
            imagePreview.classList.remove('is-hidden');
            artistCard.classList.add('has-uploaded-art');
        });

        root.querySelectorAll('[data-banner-device]').forEach((button) => {
            button.addEventListener('click', () => {
                root.querySelectorAll('[data-banner-device]').forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
                preview?.classList.toggle('is-mobile', button.dataset.bannerDevice === 'mobile');
            });
        });

        render();
    })();
</script>
