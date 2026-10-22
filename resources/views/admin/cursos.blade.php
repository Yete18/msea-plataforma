@extends('layouts.admin', ['activo' => 'cursos'])
@section('titulo', 'Cursos y lecciones')
@section('eyebrow', 'Dirección · Enseñanza')

@section('contenido')
<section class="panel">
  <div class="section-head"><div><h2>Cursos</h2><p>Curso → módulos → lecciones → preguntas. Los estudiantes solo ven cursos activos en los que están inscritos.</p></div></div>
  <div class="course-grid">
    @forelse($cursos as $c)
      <article class="course-card">
        <div class="course-cover {{ $c['activo'] ? '' : 'inactivo' }}"><svg class="icon icon-xl"><use href="{{ $iconos }}#i-cursos"/></svg></div>
        <div class="course-body">
          <div>
            <h3>{{ $c['titulo'] }}</h3>
            <p>{{ $c['instrumento'] ?: 'Todos los instrumentos' }} · {{ $c['nivel'] }}</p>
            <p>{{ $c['modulos'] }} módulo(s) · {{ $c['estudiantes'] }} inscrito(s)</p>
            @unless($c['activo'])<span class="estado-tag devuelta">Inactivo</span>@endunless
          </div>
          <a class="btn-msea small" href="{{ url('/dashboard-admin/cursos/'.$c['id']) }}">Gestionar</a>
        </div>
      </article>
    @empty
      <div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-cursos"/></svg><strong>Todavía no hay cursos.</strong><p>Crea el primero con el formulario de abajo.</p></div>
    @endforelse
  </div>
</section>

<section class="panel">
  <div class="section-head"><div><h2>Nuevo curso</h2></div></div>
  <form action="{{ url('/dashboard-admin/cursos') }}" method="POST" class="teacher-form">
    @csrf
    @include('admin.partials.campos-curso', ['curso' => null])
    <div class="form-actions"><button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Crear curso</button></div>
  </form>
</section>
@endsection
