{{--
  Estructura comun de los paneles (UX-05, UX-08).
  <x-panel.layout titulo="..." eyebrow="..." rol="Director" :nombre="..." :detalle="..." inicio="/dashboard-admin">
    <x-slot:nav> enlaces <a ...> </x-slot:nav>
    <x-slot:lateral> opcional: bajo los datos del usuario (p. ej. la barra de XP) </x-slot:lateral>
    <x-slot:acciones> botones de la barra </x-slot:acciones>
    contenido
  </x-panel.layout>
--}}
@props(['titulo', 'eyebrow' => null, 'rol' => '', 'nombre' => '', 'detalle' => '', 'inicio' => '/', 'navEtiqueta' => 'Secciones del panel'])
<div class="app" data-panel-app>
  <a class="saltar" href="#contenido">Saltar al contenido</a>

  <aside class="app-lateral" id="menu-panel" aria-label="Menú principal">
    <a class="app-marca" href="{{ url($inicio) }}">
      <img src="{{ asset('assets/img/logo-320.webp') }}" width="40" height="40" alt="" />
      <span>MSEA</span>
    </a>
    <div class="app-usuario">
      <span class="eyebrow">{{ $rol }}</span>
      <strong>{{ $nombre }}</strong>
      @if($detalle)<span>{{ $detalle }}</span>@endif
    </div>
    {{ $lateral ?? '' }}
    <nav class="app-nav" aria-label="{{ $navEtiqueta }}">
      {{ $nav }}
    </nav>
    <form action="{{ url('/logout') }}" method="POST">
      @csrf
      <button class="app-salir" type="submit"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-salir"/></svg> Cerrar sesión</button>
    </form>
  </aside>
  <div class="app-velo" data-cerrar-menu></div>

  <div class="app-cuerpo">
    <header class="app-barra">
      <button class="btn btn--sec btn--icono app-menu-btn" type="button" data-abrir-menu aria-controls="menu-panel" aria-expanded="false">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-menu"/></svg><span class="sr-only">Abrir menú</span>
      </button>
      <div class="app-barra__titulo">
        @if($eyebrow)<span class="eyebrow">{{ $eyebrow }}</span>@endif
        <h1>{{ $titulo }}</h1>
      </div>
      <div class="app-barra__acciones">
        {{ $acciones ?? '' }}
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Activar modo oscuro" aria-pressed="false"></button>
      </div>
    </header>

    <main id="contenido" class="app-main" tabindex="-1">
      <div class="app-alertas">
        <x-alertas-sesion />
      </div>
      {{ $slot }}
    </main>
  </div>
</div>
