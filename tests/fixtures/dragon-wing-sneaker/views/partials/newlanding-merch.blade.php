<section class="merch dragon-rehearsal" id="coleccion" aria-labelledby="merch-title">
  <div class="merch-inner">
    <header class="merch-heading">
      <h2 id="merch-title">Tu próximo paso.</h2>
      <p class="merch-count"><span>01</span> PIEZA</p>
    </header>
    <p class="merch-preview-note">Vista de ensayo · Puedes ver las fotos y elegir talla. Los pagos todavía no están conectados.</p>
    <div class="dragon-product">
      <button class="dragon-open" type="button" aria-haspopup="dialog" aria-controls="dragon-dialog" aria-label="Ver fotos y tallas de {{ $dragon['name'] }}">
        <img src="{{ $dragonPhotos[0]['src'] }}" alt="{{ $dragon['name'] }}: {{ $dragonPhotos[0]['label'] }}" width="1200" height="1200" loading="lazy">
        <span class="merch-view">Ver fotos y tallas <span aria-hidden="true">↗</span></span>
      </button>
      <div class="dragon-summary">
        <p class="merch-eyebrow">DRAGON WING</p>
        <h3>{{ $dragon['name'] }}</h3>
        <p class="dragon-price">US${{ number_format($dragon['price_cents'] / 100, 2) }} <span>USD</span></p>
        <p>Tallas US3–US13</p>
        <p class="dragon-muted">Precio del producto. Envío e impuestos por confirmar.</p>
        <button class="primary dragon-open" type="button" aria-haspopup="dialog" aria-controls="dragon-dialog">Elegir talla <span aria-hidden="true">↗</span></button>
      </div>
    </div>
  </div>
</section>

<dialog class="merch-dialog dragon-dialog" id="dragon-dialog" aria-labelledby="dragon-title" aria-describedby="dragon-description">
  <button class="merch-close" type="button" aria-label="Cerrar producto" autofocus>×</button>
  <div class="merch-dialog-layout">
    <div class="dragon-gallery">
      <img class="dragon-photo" src="{{ $dragonPhotos[0]['src'] }}" alt="{{ $dragon['name'] }}: {{ $dragonPhotos[0]['label'] }}" width="1200" height="1200">
      <div class="dragon-thumbnails" role="group" aria-label="Vistas del producto">
        @foreach ($dragonPhotos as $photo)
          <button type="button" data-photo="{{ $loop->index }}" aria-label="{{ $photo['label'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
            <img src="{{ $photo['src'] }}" alt="" width="1200" height="1200" loading="lazy">
          </button>
        @endforeach
      </div>
    </div>
    <div class="merch-dialog-copy">
      <p class="merch-eyebrow">TIENDA DE ENSAYO</p>
      <h2 id="dragon-title">{{ $dragon['name'] }}</h2>
      <p class="dragon-price">US${{ number_format($dragon['price_cents'] / 100, 2) }} <span>USD</span></p>
      <p id="dragon-description" class="dragon-muted">Envío e impuestos por confirmar. Las tallas se verificarán con el producto de Yoycol.</p>
      <fieldset class="dragon-sizes">
        <legend>Elige tu talla US</legend>
        <div>
          @foreach ($dragon['requested_sizes'] as $size)
            <label><input type="radio" name="dragon-size" value="{{ $size }}"><span>{{ $size }}</span></label>
          @endforeach
        </div>
      </fieldset>
      <p class="dragon-choice" role="status">Selecciona una talla para el ensayo.</p>
      <button class="primary dragon-pay" type="button" disabled>Pago de prueba pendiente</button>
      <p class="dragon-muted">Esta vista no cobra ni envía pedidos a fabricar.</p>
    </div>
  </div>
</dialog>
