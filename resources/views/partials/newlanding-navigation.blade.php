@php
  $navigation = [
    ['label' => 'Shop', 'url' => route('store')],
    ['label' => 'Shows', 'url' => route('shows')],
    ['label' => 'Royal Pass', 'url' => '#royal-pass'],
    ['label' => 'Music', 'url' => route('music')],
    ['label' => 'My Account', 'url' => route('account.show')],
  ];
@endphp
<header class="masthead" role="banner">
  <a class="signature" href="#top" aria-label="Reny Renteria, inicio">
    <img src="/newlanding-assets/assets/signature-black.png" alt="Reny Renteria" width="170" height="64">
  </a>
  <nav class="desktop-nav" aria-label="Navegación principal">
    @foreach ($navigation as $item)
      <a href="{{ $item['url'] }}" @class(['nav-account' => $loop->last])>{{ $item['label'] }}</a>
    @endforeach
  </nav>
  <details class="mobile-nav" name="site-disclosure" data-disclosure>
    <summary id="mobile-menu-label"><span>Menú</span><span class="menu-icon" aria-hidden="true"></span></summary>
    <nav aria-label="Navegación principal móvil">
      @foreach ($navigation as $item)
        <a href="{{ $item['url'] }}">{{ $item['label'] }}<span aria-hidden="true">↗</span></a>
      @endforeach
    </nav>
  </details>
</header>
