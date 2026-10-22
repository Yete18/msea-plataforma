@extends('layouts.publico', [
  'seoTitulo' => 'Sin permiso | MSEA',
  'seoDescripcion' => 'No tienes permiso para ver esta página.',
])

@section('contenido')
  <div class="error-pagina">
    <p class="codigo" aria-hidden="true">403</p>
    <h1>No tienes permiso para ver esta página</h1>
    <p>Tu cuenta no puede abrir esta página. Si crees que es un error, avisa a tu profesor o a la dirección.</p>
    <div class="acciones">
      <a class="btn-pub" href="{{ url('/') }}">Volver al inicio</a>
    </div>
  </div>
@endsection
