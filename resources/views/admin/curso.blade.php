@extends('layouts.admin', ['activo' => 'cursos'])
@section('titulo', $curso->titulo)
@section('eyebrow', 'Dirección · Enseñanza')
@section('acciones')<a class="btn-msea secondary small" href="{{ url('/dashboard-admin/cursos') }}"><svg class="icon"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Todos los cursos</a>@endsection

@section('contenido')
<section class="panel">
  <div class="section-head"><div><h2>Datos del curso</h2></div></div>
  <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso) }}" method="POST" class="teacher-form">
    @csrf @method('PUT')
    @include('admin.partials.campos-curso', ['curso' => $curso])
    <div class="form-actions"><button class="btn-msea" type="submit">Guardar curso</button></div>
  </form>
</section>

<section class="panel">
  <div class="section-head"><div><h2>Módulos y lecciones</h2><p>Si una lección ya tiene avance de estudiantes, "Eliminar" la despublica para no perder el historial.</p></div></div>

  @forelse($curso->modulos as $modulo)
    <article class="modulo-admin {{ $modulo->publicado ? '' : 'despublicado' }}">
      <header>
        <h3>{{ $modulo->orden }}. {{ $modulo->titulo }} @unless($modulo->publicado)<span class="estado-tag devuelta">No publicado</span>@endunless</h3>
        <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos/'.$modulo->id_modulo) }}" method="POST" class="inline-action">
          @csrf @method('DELETE')
          <button class="btn-msea small danger" type="submit" data-confirmar="¿Eliminar el módulo {{ $modulo->titulo }}?">Eliminar módulo</button>
        </form>
      </header>
      <details class="inline-editor">
        <summary>Editar módulo</summary>
        <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos/'.$modulo->id_modulo) }}" method="POST" class="compact-form">
          @csrf @method('PUT')
          <label><span>Título</span><input name="titulo" value="{{ $modulo->titulo }}" required maxlength="150" /></label>
          <label><span>Orden</span><input type="number" name="orden" value="{{ $modulo->orden }}" min="1" max="999" /></label>
          <label><span>Descripción</span><textarea name="descripcion" maxlength="1000">{{ $modulo->descripcion }}</textarea></label>
          <label class="inline"><input type="hidden" name="publicado" value="0" /><input type="checkbox" name="publicado" value="1" @checked($modulo->publicado) /> Publicado</label>
          <button class="btn-msea small" type="submit">Guardar</button>
        </form>
      </details>

      <ol class="lecciones-admin">
        @foreach($modulo->lecciones as $leccion)
          <li class="{{ $leccion->publicado ? '' : 'despublicado' }}">
            <div class="leccion-admin-cabecera">
              <strong>{{ $leccion->titulo }}</strong>
              <span class="pill">{{ ucfirst($leccion->tipo) }} · {{ $leccion->xp }} XP · {{ $leccion->teoria->count() }} pregunta(s)</span>
              @unless($leccion->publicado)<span class="estado-tag devuelta">No publicada</span>@endunless
            </div>
            <details class="inline-editor">
              <summary>Editar lección</summary>
              <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos/'.$modulo->id_modulo.'/lecciones/'.$leccion->id_leccion) }}" method="POST" class="compact-form">
                @csrf @method('PUT')
                @include('admin.partials.campos-leccion', ['leccion' => $leccion])
                <button class="btn-msea small" type="submit">Guardar lección</button>
              </form>
              <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos/'.$modulo->id_modulo.'/lecciones/'.$leccion->id_leccion) }}" method="POST" class="inline-action">
                @csrf @method('DELETE')
                <button class="btn-msea small danger" type="submit" data-confirmar="¿Eliminar la lección {{ $leccion->titulo }}?">Eliminar lección</button>
              </form>
            </details>

            <details class="inline-editor">
              <summary>Preguntas ({{ $leccion->teoria->count() }})</summary>
              @foreach($leccion->teoria as $pregunta)
                <form action="{{ url('/dashboard-admin/preguntas/'.$pregunta->id_teoria) }}" method="POST" class="compact-form pregunta-form">
                  @csrf
                  <input type="hidden" name="id_leccion" value="{{ $leccion->id_leccion }}" />
                  @include('admin.partials.campos-pregunta', ['pregunta' => $pregunta])
                  <button class="btn-msea small secondary" type="submit">Guardar pregunta</button>
                </form>
              @endforeach
              <form action="{{ url('/dashboard-admin/preguntas') }}" method="POST" class="compact-form pregunta-form">
                @csrf
                <input type="hidden" name="id_leccion" value="{{ $leccion->id_leccion }}" />
                <h4>Nueva pregunta</h4>
                @include('admin.partials.campos-pregunta', ['pregunta' => null])
                <button class="btn-msea small" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar pregunta</button>
              </form>
            </details>
          </li>
        @endforeach
      </ol>

      <details class="inline-editor">
        <summary><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar lección</summary>
        <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos/'.$modulo->id_modulo.'/lecciones') }}" method="POST" class="compact-form">
          @csrf
          @include('admin.partials.campos-leccion', ['leccion' => null])
          <button class="btn-msea small" type="submit">Agregar lección</button>
        </form>
      </details>
    </article>
  @empty
    <div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-capas"/></svg><strong>Este curso aún no tiene módulos.</strong></div>
  @endforelse

  <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/modulos') }}" method="POST" class="compact-form card-form">
    @csrf
    <h3>Nuevo módulo</h3>
    <label><span>Título</span><input name="titulo" required maxlength="150" /></label>
    <label><span>Descripción</span><textarea name="descripcion" maxlength="1000"></textarea></label>
    <button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar módulo</button>
  </form>
</section>

<section class="panel">
  <div class="section-head"><div><h2>Inscripciones</h2><p>{{ $inscritos->count() }} estudiante(s) inscrito(s). Retirar a un estudiante conserva su avance.</p></div></div>
  <div class="split-grid">
    <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/inscripciones') }}" method="POST" class="compact-form card-form">
      @csrf
      <h3>Inscribir</h3>
      <label><span>Un elenco completo</span>
        <select name="id_elenco"><option value="">Ninguno</option>@foreach($elencos as $e)<option value="{{ $e->id_elenco }}">{{ $e->nombre }}</option>@endforeach</select>
      </label>
      <label><span>Estudiantes</span>
        <select name="estudiantes[]" multiple size="8">@foreach($estudiantes as $e)<option value="{{ $e->id_estudiante }}">{{ $e->nombres }} {{ $e->apellido_paterno }}</option>@endforeach</select>
        <small>Ctrl o Cmd + clic para elegir varios.</small>
      </label>
      <button class="btn-msea" type="submit">Inscribir</button>
    </form>
    <ul class="lista-auditoria">
      @forelse($inscritos as $i)
        <li>
          <span><strong>{{ $i->nombres }} {{ $i->apellido_paterno }}</strong> <small>desde {{ \Illuminate\Support\Carbon::parse($i->inscrito_at)->format('d/m/Y') }}</small></span>
          <form action="{{ url('/dashboard-admin/cursos/'.$curso->id_curso.'/inscripciones/'.$i->id_estudiante) }}" method="POST" class="inline-action">
            @csrf @method('DELETE')
            <button class="btn-msea small secondary" type="submit" data-confirmar="¿Retirar a {{ $i->nombres }} del curso?">Retirar</button>
          </form>
        </li>
      @empty
        <li><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-usuarios"/></svg><strong>Nadie inscrito todavía.</strong></div></li>
      @endforelse
    </ul>
  </div>
</section>
@endsection
