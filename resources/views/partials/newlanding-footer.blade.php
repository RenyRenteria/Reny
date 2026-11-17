<footer class="site-footer">
  <div class="footer-main">
    <a class="signature" href="#top" aria-label="Reny Renteria, volver al inicio">
      <img src="/newlanding-assets/assets/signature-black.png" alt="Reny Renteria" width="170" height="64" loading="lazy" decoding="async">
    </a>
    <nav class="footer-nav" aria-label="Información y ayuda">
      <a href="{{ route('contacto') }}">Contacto</a>
      <div class="footer-social" role="group" aria-label="Redes sociales">
        <a href="https://www.instagram.com/renyrenteria/" target="_blank" rel="noopener noreferrer" aria-label="Instagram (abre otra pestaña)" title="Instagram">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
            <rect x="3" y="3" width="18" height="18" rx="5"></rect>
            <circle cx="12" cy="12" r="4"></circle>
            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"></circle>
          </svg>
        </a>
        <a href="https://www.youtube.com/renyrenteriam" target="_blank" rel="noopener noreferrer" aria-label="YouTube (abre otra pestaña)" title="YouTube">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
            <rect x="2" y="5" width="20" height="14" rx="4"></rect>
            <path d="m10 9 5 3-5 3Z" fill="currentColor" stroke="none"></path>
          </svg>
        </a>
      </div>
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
