{{-- Aviso de cookies. Solo para paginas publicas (nunca se incluye en los paneles).
     Cookies necesarias: sesion y seguridad (no requieren permiso). Si la fundacion activa
     una analitica (MSEA_ANALITICA_*), la persona elige; sin analitica el aviso solo informa. --}}
@php
  $ga4 = (string) config('msea.sitio.analitica.ga4');
  $plausible = (string) config('msea.sitio.analitica.plausible_dominio');
  $hayAnalitica = $ga4 !== '' || $plausible !== '';
@endphp
@once<link rel="stylesheet" href="{{ asset('css/publico-comun.css') }}" />@endonce
<section id="cookie-banner" class="cookie-banner" aria-label="Aviso de cookies" hidden
         data-ga4="{{ $ga4 }}" data-plausible="{{ $plausible }}" data-plausible-script="{{ config('msea.sitio.analitica.plausible_script') }}">
  <div class="cookie-texto">
    <h2 class="cookie-titulo">Tu privacidad</h2>
    @if($hayAnalitica)
      <p>Usamos cookies necesarias para iniciar sesión y proteger tu cuenta. Con tu permiso también usamos una analítica
        sencilla para saber qué páginas se visitan y mejorar el sitio. Nunca se activa dentro de tu panel.
        <a href="{{ url('/politica-de-cookies') }}">Más información</a>.</p>
    @else
      <p>Este sitio solo usa cookies necesarias para iniciar sesión y proteger tu cuenta, y recuerda tu preferencia de
        tema claro u oscuro en tu navegador. No usamos publicidad ni rastreo.
        <a href="{{ url('/politica-de-cookies') }}">Más información</a>.</p>
    @endif
  </div>
  <div class="cookie-acciones">
    @if($hayAnalitica)
      <button type="button" class="cookie-btn cookie-btn-sec" data-cookies="rechazar">Solo necesarias</button>
      <button type="button" class="cookie-btn cookie-btn-pri" data-cookies="aceptar">Aceptar analítica</button>
    @else
      <button type="button" class="cookie-btn cookie-btn-pri" data-cookies="entendido">Entendido</button>
    @endif
  </div>
</section>
<script src="{{ asset('js/cookies.js') }}" defer></script>
