@extends('layouts.publico', [
  'seoTitulo' => 'Página no encontrada | MSEA',
  'seoDescripcion' => 'La página que buscas no existe o cambió de lugar.',
])

@section('contenido')
  <div class="error-pagina">
    <p class="codigo" aria-hidden="true">404</p>
    <h1>No encontramos esta página</h1>
    <p>La dirección que escribiste no existe o cambió de lugar. Revisa que esté bien escrita, o vuelve al inicio para
      seguir practicando.</p>
    <div class="acciones">
      <a class="btn-pub" href="{{ url('/') }}">Volver al inicio</a>
      <a href="{{ url('/login') }}">Entrar a mi cuenta</a>
    </div>
  </div>
@endsection
