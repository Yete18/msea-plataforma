@extends('layouts.publico', [
  'seoTitulo' => 'Error del servidor | MSEA',
  'seoDescripcion' => 'Algo salió mal de nuestro lado.',
])

@section('contenido')
  <div class="error-pagina">
    <p class="codigo" aria-hidden="true">500</p>
    <h1>Algo salió mal de nuestro lado</h1>
    <p>Ya quedó registrado el problema. Intenta de nuevo en unos minutos.</p>
    <div class="acciones">
      <a class="btn-pub" href="{{ url('/') }}">Volver al inicio</a>
    </div>
  </div>
@endsection
