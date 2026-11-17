@php
    // Visual concepts only. Replace with confirmed catalog entries before enabling purchases.
    $merchConcepts = [
        ['image' => '01-camiseta-takeover', 'name' => 'Camiseta Takeover', 'detail' => 'Negro · Takeover'],
        ['image' => '02-hoodie-rubi', 'name' => 'Hoodie Rubí', 'detail' => 'Rubí · Reny'],
        ['image' => '03-gorra-reny', 'name' => 'Gorra Reny', 'detail' => 'Negro · Reny'],
        ['image' => '04-camiseta-marfil', 'name' => 'Camiseta Marfil', 'detail' => 'Marfil · Reny'],
        ['image' => '05-tote-reny', 'name' => 'Tote Reny', 'detail' => 'Marfil · Reny'],
        ['image' => '06-bomber-takeover', 'name' => 'Bomber Takeover', 'detail' => 'Negro · Takeover'],
        ['image' => '07-panuelo-rubi', 'name' => 'Pañuelo Rubí', 'detail' => 'Rubí · Dragón'],
        ['image' => '08-gorro-reny', 'name' => 'Gorro Reny', 'detail' => 'Negro · Reny'],
        ['image' => '09-poster-takeover', 'name' => 'Póster Takeover', 'detail' => 'Marfil · Takeover'],
    ];
@endphp

<section class="merch" id="coleccion" aria-labelledby="merch-title">
  <div class="merch-inner">
    <header class="merch-heading">
      <div>
        <h2 id="merch-title">Vístelo a tu manera.</h2>
      </div>
      <p class="merch-count"><span>09</span> PIEZAS</p>
    </header>
    <p class="merch-preview-note">Propuesta visual · Fotografía, piezas y nombres provisionales</p>
    <ol class="merch-grid" aria-label="Nueve piezas de la propuesta de colección">
      @foreach ($merchConcepts as $piece)
        <li class="merch-item">
          <a class="merch-piece"
             href="/newlanding-assets/merch/{{ $piece['image'] }}-960.webp"
             data-merch-preview
             data-piece-name="{{ $piece['name'] }}"
             data-piece-detail="{{ $piece['detail'] }}"
             aria-label="Ver propuesta: {{ $piece['name'] }}">
            <span class="merch-media">
              <img src="/newlanding-assets/merch/{{ $piece['image'] }}-480.webp"
                   srcset="/newlanding-assets/merch/{{ $piece['image'] }}-480.webp 480w, /newlanding-assets/merch/{{ $piece['image'] }}-960.webp 960w"
                   sizes="(max-width: 700px) calc((100vw - 60px) / 2), (max-width: 900px) calc((100vw - 96px) / 3), (max-width: 1600px) 29vw, 464px"
                   alt="Propuesta de {{ $piece['name'] }}, {{ $piece['detail'] }}"
                   width="960" height="960" loading="lazy" decoding="async">
              <span class="merch-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
              <span class="merch-view" aria-hidden="true">Ver pieza <span>↗</span></span>
            </span>
            <span class="merch-info">
              <span class="merch-piece-name">{{ $piece['name'] }}</span>
              <span class="merch-piece-detail">{{ $piece['detail'] }}</span>
            </span>
          </a>
        </li>
      @endforeach
    </ol>
    <footer class="merch-footer">
      <p>Un poco de Reny.<br><span>Mucho de ti.</span></p>
      <a class="primary merch-shop-all" href="{{ route('store') }}">Ver toda la tienda <span aria-hidden="true">↗</span></a>
    </footer>
  </div>
</section>

<dialog class="merch-dialog" id="merch-dialog" aria-labelledby="merch-dialog-title" aria-describedby="merch-dialog-description">
  <button class="merch-close" type="button" aria-label="Cerrar vista de la pieza" autofocus>×</button>
  <div class="merch-dialog-layout">
    <img class="merch-dialog-image" alt="" width="960" height="960">
    <div class="merch-dialog-copy">
      <p class="merch-eyebrow">PROPUESTA DE COLECCIÓN</p>
      <h2 id="merch-dialog-title"></h2>
      <p class="merch-dialog-detail"></p>
      <p id="merch-dialog-description">Una primera mirada a lo que viene. El diseño final, precio y disponibilidad de esta pieza están por confirmar.</p>
      <a class="primary" href="{{ route('store') }}">Explorar tienda actual <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</dialog>
