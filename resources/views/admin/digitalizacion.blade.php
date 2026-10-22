@extends('layouts.admin', ['activo' => 'digitalizador'])
@section('titulo', 'Revisar: '.$dig->titulo)
@section('eyebrow', 'Dirección · Enseñanza')

@section('acciones')
  <a class="btn-msea secondary small" href="{{ url('/dashboard-admin/digitalizador') }}"><svg class="icon"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver</a>
@endsection

@section('contenido')
@php
  $nombresQuintas = [-7 => '7 bemoles', -6 => '6 bemoles', -5 => '5 bemoles', -4 => '4 bemoles', -3 => '3 bemoles', -2 => '2 bemoles', -1 => '1 bemol', 0 => 'Sin alteraciones', 1 => '1 sostenido', 2 => '2 sostenidos', 3 => '3 sostenidos', 4 => '4 sostenidos', 5 => '5 sostenidos', 6 => '6 sostenidos', 7 => '7 sostenidos'];
@endphp
<section class="panel">
  <div class="section-head">
    <div>
      <h2>{{ $dig->titulo }}</h2>
      <p>{{ $instrumento['nombre'] }} · páginas {{ implode(', ', json_decode($dig->paginas, true) ?: []) }}{{ $dig->paginas_total ? ' de '.$dig->paginas_total : '' }} · {{ $dig->motor === 'audiveris' ? 'reconocido con Audiveris' : 'digitalización manual' }}</p>
    </div>
    <div class="acciones">
      <a class="btn-msea small secondary" href="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/pdf') }}" target="_blank" rel="noopener"><svg class="icon"><use href="{{ $iconos }}#i-archivo"/></svg> Abrir el PDF</a>
      @if($paginas->contains('estado', 'error') && $motor)
        <form action="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/reprocesar') }}" method="POST" class="inline-action">@csrf<button class="btn-msea small secondary" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-refrescar"/></svg> Reintentar páginas con error</button></form>
      @endif
    </div>
  </div>
  <div class="ayuda-notacion">
    <p><strong>Cómo corregir:</strong> compara la imagen con el pentagrama de abajo y edita el texto. Cada nota es <code>Nota+octava:figura</code>, por ejemplo <code>Do#5:c</code>.
      Figuras: <code>r</code> redonda, <code>b</code> blanca, <code>n</code> negra, <code>c</code> corchea, <code>s</code> semicorchea; <code>.</code> puntillo (<code>Re5:n.</code>);
      <code>-:n</code> silencio; <code>/2</code> dedo (<code>Si4:c/1</code>); <code>|</code> barra de compás. La4 = 440 Hz.</p>
  </div>
</section>

@foreach($paginas as $p)
<section class="panel" id="pagina-{{ $p->pagina }}">
  <div class="section-head">
    <div>
      <h2>Página {{ $p->pagina }}</h2>
      <p>
        <span class="estado-tag {{ $p->estado === 'reconocida' ? 'entregada' : ($p->estado === 'error' ? 'devuelta' : 'pendiente') }}">{{ ['pendiente' => 'Pendiente', 'reconocida' => 'Reconocida: revisar', 'manual' => 'Escribir a mano', 'error' => 'Error'][$p->estado] ?? $p->estado }}</span>
        @if($p->mensaje) {{ $p->mensaje }} @endif
      </p>
    </div>
    <form action="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/paginas/'.$p->id_pagina.'/ejercicios') }}" method="POST" class="inline-action">@csrf<button class="btn-msea small secondary" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar ejercicio</button></form>
  </div>

  <div class="revision-pagina">
    <div class="revision-imagen">
      @if($p->imagen_path)
        <a href="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/paginas/'.$p->pagina.'/imagen') }}" target="_blank" rel="noopener" title="Abrir la imagen en grande">
          <img src="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/paginas/'.$p->pagina.'/imagen') }}" alt="Página {{ $p->pagina }} del PDF" loading="lazy" />
        </a>
      @else
        <div class="sin-imagen">
          <p class="hint">No hay imagen de esta página en el servidor.</p>
          <a class="btn-msea small" href="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/pdf') }}#page={{ $p->pagina }}" target="_blank" rel="noopener"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Ver la página {{ $p->pagina }} del PDF</a>
        </div>
      @endif
    </div>

    <div>
      @forelse($p->lista as $i => $e)
        <form class="ejercicio-revision {{ $e['publicado_id'] ? 'publicado' : '' }} teacher-form" id="ej-{{ $p->id_pagina }}-{{ $i }}" method="POST"
              action="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion.'/paginas/'.$p->id_pagina.'/ejercicios/'.$i) }}"
              data-instrumento="{{ $dig->instrumento }}" data-clave="{{ $instrumento['clave'] }}" data-escribe="{{ $instrumento['escribe'] }}">
          @csrf
          <div class="field-grid">
            <label class="wide"><span>Nombre en la biblioteca</span><input name="nombre" required maxlength="120" value="{{ $e['nombre'] }}" /></label>
            <label><span>Compás</span><select name="compas">@foreach($compases as $c)<option @selected($c === $e['compas'])>{{ $c }}</option>@endforeach</select></label>
            <label><span>Armadura</span><select name="fifths" data-armadura>@foreach($nombresQuintas as $q => $t)<option value="{{ $q }}" @selected((int) $e['fifths'] === $q)>{{ $t }}</option>@endforeach</select></label>
            <label><span>Dificultad</span><select name="dificultad">@foreach($dificultades as $k => $t)<option value="{{ $k }}" @selected($e['dificultad'] === $k)>{{ $t }}</option>@endforeach</select></label>
            <label class="wide"><span>Notas</span><textarea class="notacion" name="texto" required data-notacion spellcheck="false">{{ $e['texto'] }}</textarea></label>
          </div>
          <div class="vista-pentagrama" data-vista aria-label="Vista previa en el pentagrama"></div>
          <p class="hint" data-resumen></p>
          <div class="acciones-ej">
            <button class="btn-msea secondary small" type="submit" name="accion" value="guardar"><svg class="icon"><use href="{{ $iconos }}#i-guardar"/></svg> Guardar corrección</button>
            <button class="btn-msea small" type="submit" name="accion" value="publicar"><svg class="icon"><use href="{{ $iconos }}#i-check"/></svg> {{ $e['publicado_id'] ? 'Actualizar en la biblioteca' : 'Publicar en la biblioteca' }}</button>
            @if($e['publicado_id'])
              <a class="btn-msea small secondary" href="{{ url('/biblioteca/ejercicios/'.$e['publicado_id'].'/probar') }}" target="_blank" rel="noopener"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Probar</a>
              <span class="estado-tag calificada">En la biblioteca</span>
            @endif
          </div>
        </form>
      @empty
        <p class="hint">No hay ejercicios en esta página. Usa «Agregar ejercicio» para escribirlo.</p>
      @endforelse
    </div>
  </div>
</section>
@endforeach

<section class="panel">
  <form action="{{ url('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion) }}" method="POST" class="inline-action">
    @csrf @method('DELETE')
    <button class="btn-msea small danger" type="submit" data-confirmar="¿Eliminar esta digitalización y su PDF? Los ejercicios publicados se conservan."><svg class="icon"><use href="{{ $iconos }}#i-borrar"/></svg> Eliminar digitalización y PDF</button>
  </form>
</section>

@endsection

@push('estilos')<link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}" />@endpush
@push('scripts')<script defer src="{{ asset('js/digitalizador.js') }}"></script>@endpush
