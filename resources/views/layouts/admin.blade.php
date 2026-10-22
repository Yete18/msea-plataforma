<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('titulo', 'Panel del director') · MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}" />
  @stack('estilos')
</head>
<body>
<x-panel.layout
  :titulo="html_entity_decode(trim($__env->yieldContent('titulo', 'Panel del director')), ENT_QUOTES)"
  :eyebrow="trim($__env->yieldContent('eyebrow', 'Dirección'))"
  rol="Director"
  :nombre="trim(session('nombre').' '.session('apellido_paterno'))"
  :detalle="session('correo')"
  inicio="/dashboard-admin"
  nav-etiqueta="Secciones del director">
  <x-slot:nav>@include('partials.admin-nav', ['activo' => $activo ?? ''])</x-slot:nav>
  <x-slot:acciones>@yield('acciones')</x-slot:acciones>
  @yield('contenido')
</x-panel.layout>
<script defer src="{{ asset('js/admin-gestion.js') }}"></script>
@stack('scripts')
</body>
</html>
