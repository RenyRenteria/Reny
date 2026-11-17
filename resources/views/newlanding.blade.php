<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#201D1E">
  <meta name="robots" content="noindex, nofollow">
  <title>Reny Renteria Takeover · Nuevo inicio</title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="/newlanding-assets/styles.css?v=20261117-royal-pass">
</head>
<body>
  <main>
    <section class="hero" aria-labelledby="concert-title">
      <picture class="hero-art" aria-hidden="true">
        <source media="(max-width: 900px)" srcset="/newlanding-assets/assets/takeover-fondo-movil.png">
        <img src="/newlanding-assets/assets/takeover-fondo-escritorio.png" alt="" fetchpriority="high">
      </picture>
      <div class="shade" aria-hidden="true"></div>
      <header class="masthead">
        <a class="signature" href="#concert-title" aria-label="Reny Renteria, inicio">
          <img src="/newlanding-assets/assets/signature-black.png" alt="Reny Renteria" width="170" height="64">
        </a>
        <span class="masthead-caption">EL PRÓXIMO ENCUENTRO</span>
        <a class="top-link" href="#concert-details">02.10.27 <span aria-hidden="true">↗</span></a>
      </header>
      <div class="copy">
        <p class="eyebrow"><span aria-hidden="true"></span> RENY EN VIVO</p>
        <h1 id="concert-title"><span class="artist-name">Reny Renteria</span><span class="show-name">TAKEOVER</span></h1>
        <p class="tagline">Una noche para vivirla de cerca.</p>
        <div class="details" id="concert-details">
          <p>Sábado 2 de octubre de 2027</p>
          <p class="venue">Rock &amp; Folk</p>
        </div>
        <div class="countdown" aria-label="Cuenta regresiva hasta el 2 de octubre de 2027 a las 8:30 p. m., hora de Panamá">
          <div><span class="number" data-unit="days">—</span><span class="unit">DÍAS</span></div>
          <div><span class="number" data-unit="hours">—</span><span class="unit">HORAS</span></div>
          <div><span class="number" data-unit="minutes">—</span><span class="unit">MIN</span></div>
          <div><span class="number" data-unit="seconds">—</span><span class="unit">SEG</span></div>
        </div>
        <p class="countdown-note" id="countdown-note">Show 8:30 p. m. · Hora de Panamá</p>
        <div class="actions">
          <a class="primary" href="{{ route('store.checkout', ['product' => 'reny-renteria-takeover-2027']) }}">Comprar entradas <span aria-hidden="true">↗</span></a>
          <a class="calendar" href="/newlanding-assets/reny-takeover.ics" download>Guardar fecha <span aria-hidden="true">↗</span></a>
        </div>
      </div>
      <footer class="hero-footer"><span>UNA NOCHE. TODO RENY.</span><span>ROCK &amp; FOLK <i aria-hidden="true">·</i> 02 OCT 2027</span></footer>
    </section>
    @include('partials.newlanding-merch-banner')
    @include('partials.newlanding-merch')
    @include('partials.newlanding-royal-pass')
  </main>
  <script src="/newlanding-assets/script.js?v=20261116-merch" defer></script>
</body>
</html>
