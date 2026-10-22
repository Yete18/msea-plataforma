<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48" />
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}" />
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
  <link rel="manifest" href="{{ asset('site.webmanifest') }}" />
  <meta name="theme-color" content="#8CC63F" />
  @include('partials.seo')
  <link rel="stylesheet" href="{{ asset('css/fuentes.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/publico.css') }}" />
</head>
<body class="pagina-publica">
  <a class="saltar" href="#contenido">Saltar al contenido</a>

  <header class="pub-cabecera">
    <a href="{{ url('/') }}" class="pub-marca" aria-label="MSEA, ir al inicio">
      <img src="{{ asset('assets/img/logo-320.webp') }}" width="44" height="44" alt="Logotipo de la Fundación MSEA" />
      <span><strong>MSEA</strong><small>Movimiento Sinfónico de El Alto</small></span>
    </a>
    <a href="{{ url('/login') }}" class="pub-entrar">Iniciar sesión</a>
  </header>

  <main id="contenido" class="pub-contenido">
    @yield('contenido')
  </main>

  <footer class="pub-pie">
    @include('partials.enlaces-legales')
    <p>© {{ now()->year }} {{ config('msea.sitio.razon_social') }}</p>
  </footer>

  @include('partials.whatsapp')
  @include('partials.cookies')
</body>
</html>
