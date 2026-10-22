{{-- Recursos de las páginas de acceso (login, registro y recuperación): el mismo
     sistema de diseño de los paneles, sin Bootstrap ni Lucide. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48" />
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}" />
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
<link rel="manifest" href="{{ asset('site.webmanifest') }}" />
<meta name="theme-color" content="#315C3A" />
@include('partials.tema-inicial')
<meta name="msea-iconos" content="{{ $iconos }}" />
<link rel="stylesheet" href="{{ asset('css/fuentes.css') }}" />
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}" />
<link rel="stylesheet" href="{{ asset('css/componentes.css') }}" />
<link rel="stylesheet" href="{{ asset('css/auth.css') }}" />
<script defer src="{{ asset('js/theme-toggle.js') }}"></script>
<script defer src="{{ asset('js/panel-layout.js') }}"></script>
