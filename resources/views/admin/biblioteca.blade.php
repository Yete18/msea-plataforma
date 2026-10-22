@extends('layouts.admin', ['activo' => 'biblioteca'])
@section('titulo', 'Biblioteca técnica')
@section('eyebrow', 'Dirección · Enseñanza')

@section('contenido')
@php($inst = \App\Services\EscalaService::INSTRUMENTOS[$instrumento])
<nav class="filtros-instrumento" aria-label="Instrumento">
  @foreach($instrumentos as $clave => $nombre)
    <a href="{{ url('/dashboard-admin/biblioteca?instrumento='.$clave) }}" class="btn-msea small {{ $clave === $instrumento ? '' : 'secondary' }}" @if($clave === $instrumento) aria-current="page" @endif><svg class="icon"><use href="{{ $iconos }}#i-{{ $clave }}"/></svg> {{ $nombre }}</a>
  @endforeach
</nav>

<section class="panel">
  <div class="section-head">
    <div>
      <h2>Escalas de {{ mb_strtolower($inst['nombre']) }}</h2>
      <p>Las ven solo los profesores, que las asignan como tareas indicando octavas, ritmo y tempo. Las frecuencias se calculan con La4 = 440 Hz. Clave de {{ $inst['clave'] }}{{ $inst['escribe'] ? ' (se escribe una octava más aguda de lo que suena)' : '' }}. Cuerdas: {{ collect($inst['cuerdas'])->map(fn ($m, $c) => $c)->implode(', ') }}.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Escala</th><th>Dificultad</th><th>Octavas</th><th>Armadura</th><th>En tareas</th><th>Acciones</th></tr></thead>
      <tbody>
      @forelse($escalas as $e)
        <tr id="escala-{{ $e['id'] }}" class="{{ $e['activo'] ? '' : 'inactivo' }}">
          <td><strong>{{ $e['nombre'] }}</strong><small>{{ implode(' ', $e['notas_una_octava']) }}</small>@unless($e['activo'])<span class="estado-tag devuelta">Desactivada</span>@endunless</td>
          <td>{{ $e['dificultad_texto'] }}</td>
          <td>@foreach($e['rangos'] as $oct => $rango)<small>{{ $oct }}: {{ $rango }}</small>@endforeach</td>
          <td>{{ $e['armadura']['texto'] }}</td>
          <td>{{ $e['usos'] }}</td>
          <td class="acciones">
            <a class="btn-msea small secondary" href="{{ url('/biblioteca/escalas/'.$e['id'].'/probar') }}" target="_blank" rel="noopener"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Probar</a>
            <form action="{{ url('/dashboard-admin/biblioteca/escalas/'.$e['id'].'/estado') }}" method="POST" class="inline-action">
              @csrf
              <input type="hidden" name="activo" value="{{ $e['activo'] ? 0 : 1 }}" />
              <button class="btn-msea small {{ $e['activo'] ? 'danger' : '' }}" type="submit">{{ $e['activo'] ? 'Desactivar' : 'Activar' }}</button>
            </form>
            <details class="inline-editor">
              <summary><svg class="icon"><use href="{{ $iconos }}#i-editar"/></svg> Editar</summary>
              <form action="{{ url('/dashboard-admin/biblioteca/escalas/'.$e['id']) }}" method="POST" class="teacher-form">
                @csrf
                @include('admin.partials.campos-escala', ['e' => $e])
                @if($e['tiene_digitacion'])
                  <p class="hint">Cuerda y dedo en primera posición disponibles para {{ implode(' y ', $e['tiene_digitacion']) }} octava(s). Se calculan solos.</p>
                @endif
                <div class="form-actions"><button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-guardar"/></svg> Guardar cambios</button></div>
              </form>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-afinacion"/></svg><strong>La biblioteca está vacía.</strong><p>Agrega la primera escala con el formulario de abajo.</p></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>

<section class="panel" id="nueva-escala">
  <div class="section-head"><div><h2>Agregar escala de {{ mb_strtolower($inst['nombre']) }}</h2><p>Indica la tónica y en qué octava empieza según cuántas octavas se toquen. Ejemplo en violín: La mayor empieza en La4 para una octava y en La3 para dos o tres. Usa el mismo nombre que en los otros instrumentos para que una tarea de elenco sirva a todos.</p></div></div>
  <form action="{{ url('/dashboard-admin/biblioteca/escalas') }}" method="POST" class="teacher-form">
    @csrf
    <input type="hidden" name="instrumento" value="{{ $instrumento }}" />
    @include('admin.partials.campos-escala', ['e' => null])
    <div class="form-actions"><button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar a la biblioteca</button></div>
  </form>
</section>
@endsection
