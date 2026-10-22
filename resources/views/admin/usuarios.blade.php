@extends('layouts.admin', ['activo' => 'usuarios'])
@section('titulo', 'Estudiantes y familias')
@section('eyebrow', 'Dirección · Personas')

@section('contenido')
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Estudiantes</h2>
      <p>Editar datos, nivel e instrumento; desactivar conserva su historial académico. La recuperación envía un código al correo del estudiante: nadie ve su contraseña.</p>
    </div>
    <label class="buscador"><span class="sr-only">Filtrar</span><input type="search" placeholder="Filtrar por nombre o correo" data-filtrar="#tabla-estudiantes" /></label>
  </div>
  <div class="table-wrap">
    <table id="tabla-estudiantes">
      <thead><tr><th>Estudiante</th><th>Nivel / elenco</th><th>Profesor</th><th>XP</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      @forelse($estudiantes as $e)
        <tr class="{{ $e['activo'] ? '' : 'inactivo' }}">
          <td><strong>{{ $e['nombre'] }}</strong><small>{{ $e['correo'] }}</small></td>
          <td>{{ $e['seccion'] }}<small>{{ $e['elenco'] }}</small></td>
          <td>{{ $e['profesor'] }}</td>
          <td>{{ $e['xp'] }} <small>Nivel {{ $e['nivel'] }}</small></td>
          <td><span class="estado-tag {{ $e['activo'] ? 'calificada' : 'devuelta' }}">{{ $e['activo'] ? 'Activo' : 'Inactivo' }}</span></td>
          <td class="acciones">
            <details class="inline-editor">
              <summary><svg class="icon"><use href="{{ $iconos }}#i-editar"/></svg> Editar</summary>
              <form action="{{ url('/dashboard-admin/usuarios/'.$e['id_usuario']) }}" method="POST" class="compact-form">
                @csrf @method('PUT')
                <label><span>Nombres</span><input name="nombres" value="{{ $e['nombres'] }}" required maxlength="100" /></label>
                <label><span>Apellido paterno</span><input name="apellido_paterno" value="{{ $e['apellido_paterno'] }}" maxlength="100" /></label>
                <label><span>Apellido materno</span><input name="apellido_materno" value="{{ $e['apellido_materno'] }}" maxlength="100" /></label>
                <label><span>Correo</span><input type="email" name="correo" value="{{ $e['correo'] }}" required maxlength="100" /></label>
                <label><span>Celular</span><input name="celular" value="{{ $e['celular'] }}" maxlength="20" /></label>
                <label><span>Carnet de identidad</span><input name="ci" value="{{ $e['ci'] }}" maxlength="20" autocomplete="off" /></label>
                <label><span>Dirección</span><input name="direccion" value="{{ $e['direccion'] }}" maxlength="500" autocomplete="off" /></label>
                <label><span>Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" value="{{ $e['fecha_nacimiento'] }}" /></label>
                <label><span>Nivel formativo</span>
                  <select name="id_seccion">@foreach($secciones as $s)<option value="{{ $s->id_seccion }}" @selected($s->id_seccion === $e['id_seccion'])>{{ $s->nombre }}</option>@endforeach</select>
                </label>
                <label><span>Instrumento</span>
                  <select name="instrumento"><option value="">Sin cambio</option>@foreach($instrumentos as $i)<option value="{{ $i->id_instrumento }}">{{ $i->nombre }}</option>@endforeach</select>
                </label>
                <button class="btn-msea small" type="submit">Guardar</button>
              </form>
            </details>
            @include('admin.partials.estado-usuario', ['idUsuario' => $e['id_usuario'], 'activo' => $e['activo']])
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-usuarios"/></svg><strong>Todavía no hay estudiantes.</strong></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>

<section class="panel" id="padres">
  <div class="section-head">
    <div><h2>Padres, madres y tutores</h2><p>Un padre solo puede ver a los estudiantes vinculados a su cuenta.</p></div>
  </div>

  <div class="split-grid">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Tutor</th><th>Estudiantes vinculados</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        @forelse($padres as $padre)
          <tr class="{{ $padre['activo'] ? '' : 'inactivo' }}">
            <td><strong>{{ $padre['nombre'] }}</strong><small>{{ $padre['correo'] }} · {{ $padre['parentesco'] }}</small></td>
            <td>
              @forelse($padre['hijos'] as $hijo)
                <form action="{{ url('/dashboard-admin/padres/'.$padre['id'].'/estudiantes/'.$hijo['id']) }}" method="POST" class="chip-form">
                  @csrf @method('DELETE')
                  <span class="pill">{{ $hijo['nombre'] }}</span>
                  <button type="submit" class="chip-quitar" aria-label="Desvincular a {{ $hijo['nombre'] }}" data-confirmar="¿Desvincular a {{ $hijo['nombre'] }} de esta cuenta?"><svg class="icon"><use href="{{ $iconos }}#i-cerrar"/></svg></button>
                </form>
              @empty
                <small>Sin estudiantes vinculados</small>
              @endforelse
              <form action="{{ url('/dashboard-admin/padres/'.$padre['id'].'/vincular') }}" method="POST" class="vincular-form">
                @csrf
                <label class="sr-only" for="vinc-{{ $padre['id'] }}">Estudiante a vincular</label>
                <select name="id_estudiante" id="vinc-{{ $padre['id'] }}" required>
                  <option value="">Vincular estudiante...</option>
                  @foreach($estudiantes as $e)<option value="{{ $e['id'] }}">{{ $e['nombre'] }}</option>@endforeach
                </select>
                <button class="btn-msea small secondary" type="submit">Vincular</button>
              </form>
            </td>
            <td><span class="estado-tag {{ $padre['activo'] ? 'calificada' : 'devuelta' }}">{{ $padre['activo'] ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="acciones">@include('admin.partials.estado-usuario', ['idUsuario' => $padre['id_usuario'], 'activo' => $padre['activo']])</td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-usuarios"/></svg><strong>No hay cuentas de padres todavía.</strong><p>Crea la primera con el formulario.</p></div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <form action="{{ url('/dashboard-admin/padres') }}" method="POST" class="compact-form card-form">
      @csrf
      <h3>Nueva cuenta de padre o tutor</h3>
      <label><span>Nombres</span><input name="nombres" value="{{ old('nombres') }}" required maxlength="100" /></label>
      <label><span>Apellido paterno</span><input name="apellido_paterno" value="{{ old('apellido_paterno') }}" maxlength="100" /></label>
      <label><span>Correo</span><input type="email" name="correo" value="{{ old('correo') }}" required maxlength="100" /></label>
      <label><span>Celular</span><input name="celular" value="{{ old('celular') }}" maxlength="20" /></label>
      <label><span>Parentesco</span>
        <select name="parentesco">@foreach(['Madre', 'Padre', 'Tutor', 'Tutora', 'Otro familiar'] as $op)<option @selected(old('parentesco') === $op)>{{ $op }}</option>@endforeach</select>
      </label>
      <label><span>Estudiantes a vincular</span>
        <select name="estudiantes[]" multiple size="5">@foreach($estudiantes as $e)<option value="{{ $e['id'] }}">{{ $e['nombre'] }}</option>@endforeach</select>
        <small>Ctrl o Cmd + clic para elegir varios.</small>
      </label>
      <label><span>Contraseña temporal</span><input type="password" name="contrasena" required minlength="8" autocomplete="new-password" /></label>
      <label><span>Confirmar contraseña</span><input type="password" name="contrasena_confirmation" required autocomplete="new-password" /></label>
      <small>Entrégala de forma privada; la familia puede cambiarla con "Olvidé mi contraseña".</small>
      <button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Crear cuenta</button>
    </form>
  </div>
</section>
@endsection
