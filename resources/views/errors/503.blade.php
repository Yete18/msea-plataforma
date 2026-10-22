@extends('layouts.publico', [
  'seoTitulo' => 'En mantenimiento | MSEA',
  'seoDescripcion' => 'Estamos en mantenimiento.',
])

@section('contenido')
  <div class="error-pagina">
    <p class="codigo" aria-hidden="true">503</p>
    <h1>Estamos en mantenimiento</h1>
    <p>Volveremos en unos minutos. Gracias por tu paciencia.</p>
    <div class="acciones">
      <a class="btn-pub" href="{{ url('/') }}">Volver al inicio</a>
    </div>
  </div>
@endsection
