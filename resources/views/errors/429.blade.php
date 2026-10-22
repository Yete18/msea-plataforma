@extends('layouts.publico', [
  'seoTitulo' => 'Demasiados intentos | MSEA',
  'seoDescripcion' => 'Demasiados intentos.',
])

@section('contenido')
  <div class="error-pagina">
    <p class="codigo" aria-hidden="true">429</p>
    <h1>Demasiados intentos</h1>
    <p>Hiciste muchas solicitudes seguidas. Espera un minuto y vuelve a intentarlo.</p>
    <div class="acciones">
      <a class="btn-pub" href="{{ url('/') }}">Volver al inicio</a>
    </div>
  </div>
@endsection
