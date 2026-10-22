@extends('layouts.publico', [
  'seoTitulo' => 'Política de cookies | MSEA',
  'seoDescripcion' => 'Qué cookies y datos del navegador usa la plataforma MSEA y cómo cambiar tu elección.',
  'seoIndexar' => true,
  'seoJsonLd' => [\App\Support\DatosEstructurados::migas(['Inicio' => url('/'), 'Política de cookies' => url('/politica-de-cookies')])],
])

@section('contenido')
  @php
    $s = config('msea.sitio');
    $ga4 = (string) $s['analitica']['ga4'];
    $plausible = (string) $s['analitica']['plausible_dominio'];
  @endphp
  <h1>Política de cookies</h1>
  <p class="pub-actualizado">Última actualización: {{ \Illuminate\Support\Carbon::parse($s['legal_actualizado'])->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

  <p>Una cookie es un pequeño archivo que el sitio guarda en tu navegador. Algunas son imprescindibles para que la plataforma funcione y otras
    sirven para medir visitas. MSEA no usa cookies de publicidad ni de rastreo entre sitios.</p>

  <h2>Qué usamos</h2>
  <div class="pub-tabla-envoltura" tabindex="0" role="region" aria-label="Tabla de cookies">
    <table class="pub-tabla">
      <thead><tr><th scope="col">Nombre</th><th scope="col">Para qué sirve</th><th scope="col">Tipo</th><th scope="col">Duración</th></tr></thead>
      <tbody>
        <tr><td><code>{{ config('session.cookie') }}</code></td><td>Mantiene tu sesión iniciada y protege tu cuenta.</td><td>Necesaria (cookie)</td><td>Hasta {{ (int) config('session.lifetime') }} minutos sin actividad</td></tr>
        <tr><td><code>XSRF-TOKEN</code></td><td>Protege los formularios contra envíos falsos.</td><td>Necesaria (cookie)</td><td>Sesión</td></tr>
        <tr><td><code>msea-theme</code></td><td>Recuerda si prefieres el tema claro u oscuro.</td><td>Preferencia (almacenamiento del navegador)</td><td>Hasta que la borres</td></tr>
        <tr><td><code>msea-cookies</code></td><td>Recuerda tu elección sobre este aviso.</td><td>Preferencia (almacenamiento del navegador)</td><td>Hasta que la borres</td></tr>
        <tr><td><code>msea-meta-pospuesta</code>, <code>msea-visto-…</code></td><td>Recuerdan avisos del panel del estudiante (meta diaria, logros ya vistos).</td><td>Preferencia (almacenamiento del navegador)</td><td>Hasta que la borres</td></tr>
        @if($ga4 !== '')
          <tr><td><code>_ga</code>, <code>_ga_*</code></td><td>Google Analytics: cuenta visitas a las páginas públicas de forma agregada. Solo se activa si la aceptas.
            Sin señales de publicidad ni personalización.</td><td>Analítica (opcional)</td><td>Hasta 2 años</td></tr>
        @endif
        @if($plausible !== '')
          <tr><td>—</td><td>Plausible Analytics: cuenta visitas a las páginas públicas sin cookies y sin identificar a la persona. Solo se activa si la aceptas.</td><td>Analítica (opcional)</td><td>No usa cookies</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  @if($ga4 === '' && $plausible === '')
    <p>Por ahora no usamos ninguna herramienta de analítica: solo las cookies necesarias y las preferencias de arriba.</p>
  @else
    <p>La analítica nunca se activa dentro de los paneles con sesión iniciada (estudiante, profesor, familia o dirección).</p>
  @endif

  <h2>Cómo cambiar tu elección</h2>
  <p>Puedes cambiar tu decisión cuando quieras:</p>
  <p><button type="button" class="btn-pub" data-abrir-cookies>Abrir preferencias de cookies</button></p>
  <p>También puedes borrar o bloquear las cookies desde la configuración de tu navegador. Si bloqueas las necesarias, no podrás iniciar sesión.</p>

  <p class="pub-aviso">Más información sobre tus datos en la <a href="{{ url('/politica-de-privacidad') }}">política de privacidad</a>.</p>
@endsection
