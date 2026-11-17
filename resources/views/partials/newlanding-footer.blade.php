<footer class="site-footer">
  <div class="footer-main">
    <a class="signature" href="#top" aria-label="Reny Renteria, volver al inicio">
      <img src="/newlanding-assets/assets/signature-black.png" alt="Reny Renteria" width="170" height="64" loading="lazy" decoding="async">
    </a>
    <nav class="footer-nav" aria-label="Información y ayuda">
      <a href="{{ route('contacto') }}">Contacto</a>
      <details name="site-disclosure" data-disclosure>
        <summary id="footer-social-label">Redes</summary>
        <div class="footer-panel" role="region" aria-labelledby="footer-social-label">
          <p class="footer-panel-title">Nos vemos por aquí.</p>
          <a href="https://www.instagram.com/renyrenteria/" target="_blank" rel="noopener noreferrer">Instagram <span aria-hidden="true">↗</span><span class="visually-hidden"> (abre otra pestaña)</span></a>
          <a href="https://www.youtube.com/renyrenteriam" target="_blank" rel="noopener noreferrer">YouTube <span aria-hidden="true">↗</span><span class="visually-hidden"> (abre otra pestaña)</span></a>
        </div>
      </details>
      <details name="site-disclosure" data-disclosure>
        <summary id="footer-faq-label">Preguntas frecuentes</summary>
        <div class="footer-panel" role="region" aria-labelledby="footer-faq-label">
          <p class="footer-panel-title">Antes de encontrarnos.</p>
          <details class="footer-faq">
            <summary>Cómo compro mis entradas?</summary>
            <p>Entra a <a href="{{ route('shows') }}">Shows</a>, elige el concierto y sigue el enlace de compra para consultar las opciones disponibles.</p>
          </details>
          <details class="footer-faq">
            <summary>Dónde accedo a mi Royal Pass?</summary>
            <p>Si ya eres miembro, entra a <a href="{{ route('royals') }}">Royals</a> con tu cuenta. También puedes <a href="{{ route('store.checkout', ['product' => 'royal']) }}">conocer el Royal Pass</a>.</p>
          </details>
          <details class="footer-faq">
            <summary>Ya puedo comprar esta colección?</summary>
            <p>Las nueve piezas de esta página son una propuesta visual. Consulta los productos disponibles en <a href="{{ route('store') }}">Shop</a>.</p>
          </details>
          <details class="footer-faq">
            <summary>Necesito ayuda con una compra</summary>
            <p>Escríbenos desde <a href="{{ route('contacto') }}">Contacto</a> e incluye el número de tu pedido.</p>
          </details>
        </div>
      </details>
      <a href="{{ route('bio') }}">Biography</a>
      <details name="site-disclosure" data-disclosure>
        <summary id="footer-policies-label">Políticas</summary>
        <div class="footer-panel" role="region" aria-labelledby="footer-policies-label">
          <p class="footer-panel-title">Próximamente</p>
          <p>Las políticas estarán disponibles aquí. Si tienes una consulta, <a href="{{ route('contacto') }}">contáctanos</a>.</p>
        </div>
      </details>
    </nav>
  </div>
  <div class="footer-bottom">
    <span>© {{ date('Y') }} Reny Renteria</span>
    <span class="footer-motto">LA MÚSICA NOS CONECTA.</span>
    <a href="#top">Volver arriba <span aria-hidden="true">↑</span></a>
  </div>
</footer>
