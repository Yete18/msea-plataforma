@extends('layouts.admin', ['activo' => 'catalogos'])
@section('titulo', 'Instrumentos, niveles y logros')
@section('eyebrow', 'Dirección · Enseñanza')

@section('contenido')
<section class="panel" id="instrumentos">
  <div class="section-head"><div><h2>Instrumentos</h2><p>Los cuatro instrumentos de cuerda de MSEA y cualquier otro que se agregue.</p></div></div>
  <div class="catalogo-grid">
    @foreach($instrumentos as $i)
      <form action="{{ url('/dashboard-admin/instrumentos/'.$i->id_instrumento) }}" method="POST" class="catalogo-item">
        @csrf
        <label class="sr-only" for="inst-{{ $i->id_instrumento }}">Nombre</label>
        <input id="inst-{{ $i->id_instrumento }}" name="nombre" value="{{ $i->nombre }}" required maxlength="50" />
        <small>{{ $i->usos }} usuario(s)</small>
        <button class="btn-msea small secondary" type="submit">Guardar</button>
      </form>
    @endforeach
    <form action="{{ url('/dashboard-admin/instrumentos') }}" method="POST" class="catalogo-item nuevo">
      @csrf
      <label class="sr-only" for="inst-nuevo">Nuevo instrumento</label>
      <input id="inst-nuevo" name="nombre" placeholder="Nuevo instrumento" required maxlength="50" />
      <button class="btn-msea small" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar</button>
    </form>
  </div>
</section>

<section class="panel" id="niveles">
  <div class="section-head"><div><h2>Niveles formativos</h2><p>Por ejemplo: Preparatoria, Inicial, Intermedio, Prejuvenil, Orquesta Juvenil.</p></div></div>
  <div class="catalogo-grid">
    @foreach($secciones as $s)
      <form action="{{ url('/dashboard-admin/niveles/'.$s->id_seccion) }}" method="POST" class="catalogo-item">
        @csrf
        <label class="sr-only" for="niv-{{ $s->id_seccion }}">Nombre</label>
        <input id="niv-{{ $s->id_seccion }}" name="nombre" value="{{ $s->nombre }}" required maxlength="50" />
        <small>{{ $s->estudiantes }} estudiante(s)</small>
        <button class="btn-msea small secondary" type="submit">Guardar</button>
      </form>
    @endforeach
    <form action="{{ url('/dashboard-admin/niveles') }}" method="POST" class="catalogo-item nuevo">
      @csrf
      <label class="sr-only" for="niv-nuevo">Nuevo nivel</label>
      <input id="niv-nuevo" name="nombre" placeholder="Nuevo nivel" required maxlength="50" />
      <button class="btn-msea small" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar</button>
    </form>
  </div>
</section>

<section class="panel" id="logros">
  <div class="section-head"><div><h2>Logros</h2><p>El sistema desbloquea automáticamente: primer_paso, 500_xp, nivel_4, racha_3 y racha_7. El bono de XP se otorga una sola vez por estudiante.</p></div></div>
  @foreach($logros as $l)
    <form action="{{ url('/dashboard-admin/logros/'.$l->id_logro) }}" method="POST" id="logro-{{ $l->id_logro }}">@csrf</form>
  @endforeach
  <form action="{{ url('/dashboard-admin/logros') }}" method="POST" id="logro-nuevo">@csrf</form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Código</th><th>Nombre</th><th>Descripción</th><th>Bono XP</th><th>Activo</th><th></th></tr></thead>
      <tbody>
      @foreach($logros as $l)
        <tr>
          <td><input form="logro-{{ $l->id_logro }}" name="codigo" value="{{ $l->codigo }}" required maxlength="60" aria-label="Código" /></td>
          <td><input form="logro-{{ $l->id_logro }}" name="nombre" value="{{ $l->nombre }}" required maxlength="100" aria-label="Nombre" /></td>
          <td><input form="logro-{{ $l->id_logro }}" name="descripcion" value="{{ $l->descripcion }}" maxlength="500" aria-label="Descripción" /></td>
          <td><input form="logro-{{ $l->id_logro }}" type="number" name="xp_bonus" value="{{ $l->xp_bonus }}" min="0" max="500" aria-label="Bono XP" /></td>
          <td><input type="hidden" form="logro-{{ $l->id_logro }}" name="activo" value="0" /><input form="logro-{{ $l->id_logro }}" type="checkbox" name="activo" value="1" @checked($l->activo) aria-label="Activo" /></td>
          <td><button form="logro-{{ $l->id_logro }}" class="btn-msea small secondary" type="submit">Guardar</button></td>
        </tr>
      @endforeach
        <tr>
          <td><input form="logro-nuevo" name="codigo" placeholder="codigo_unico" required maxlength="60" aria-label="Código" /></td>
          <td><input form="logro-nuevo" name="nombre" placeholder="Nombre" required maxlength="100" aria-label="Nombre" /></td>
          <td><input form="logro-nuevo" name="descripcion" placeholder="Descripción" maxlength="500" aria-label="Descripción" /></td>
          <td><input form="logro-nuevo" type="number" name="xp_bonus" value="0" min="0" max="500" aria-label="Bono XP" /></td>
          <td><input type="hidden" form="logro-nuevo" name="activo" value="1" />Sí</td>
          <td><button form="logro-nuevo" class="btn-msea small" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
@endsection
