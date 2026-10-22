@extends('layouts.admin', ['activo' => 'profesores'])
@section('titulo', 'Profesores')
@section('eyebrow', 'Dirección · Personas')
@section('acciones')
  <a href="#nuevo-profesor" class="btn"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mas"/></svg> Registrar profesor</a>
@endsection

@section('contenido')
<section class="panel" aria-labelledby="profesores-titulo">
  <div class="section-head">
    <div>
      <h2 id="profesores-titulo">Equipo docente</h2>
      <p>Cada profesor ve y califica solo a sus propios alumnos. Desactivar una cuenta le quita el acceso, pero conserva sus tareas y calificaciones.</p>
    </div>
    @if(count($profesoresData) > 4)
      <label class="buscador"><span class="sr-only">Filtrar profesores</span><input type="search" placeholder="Filtrar por nombre o correo" data-filtrar="#tabla-profesores" /></label>
    @endif
  </div>

  @if(count($profesoresData))
    <div class="table-wrap">
      <table id="tabla-profesores">
        <thead><tr><th>Profesor</th><th>Especialidad</th><th>Alumnos</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        @foreach($profesoresData as $p)
          <tr class="{{ $p['activo'] ? '' : 'inactivo' }}">
            <td><strong>{{ $p['nombre'] }}</strong><small>{{ $p['correo'] }} · {{ $p['celular'] }}</small></td>
            <td>{{ $p['especialidad'] }}</td>
            <td>
              @if($p['estudiantes'])
                <details class="inline-editor">
                  <summary>{{ $p['estudiantes'] }} {{ $p['estudiantes'] === 1 ? 'alumno' : 'alumnos' }}</summary>
                  <ul class="lista-simple">
                    @foreach($p['estudiantesLista'] as $alumno)
                      <li><strong>{{ $alumno['nombre'] }}</strong><small>{{ $alumno['elenco'] }}</small></li>
                    @endforeach
                  </ul>
                </details>
              @else
                <span class="hint">Sin alumnos</span>
              @endif
            </td>
            <td><span class="estado-tag {{ $p['activo'] ? 'ok' : 'inactivo' }}">{{ $p['activo'] ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="acciones">
              <details class="inline-editor">
                <summary><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-editar"/></svg> Editar</summary>
                <form action="{{ url('/dashboard-admin/usuarios/'.$p['id_usuario']) }}" method="POST" class="compact-form">
                  @csrf @method('PUT')
                  <label>Nombres <input name="nombres" value="{{ $p['nombres'] }}" required maxlength="100" autocomplete="off" /></label>
                  <label>Apellido paterno <input name="apellido_paterno" value="{{ $p['apellido_paterno'] }}" maxlength="100" autocomplete="off" /></label>
                  <label>Apellido materno <input name="apellido_materno" value="{{ $p['apellido_materno'] }}" maxlength="100" autocomplete="off" /></label>
                  <label>Correo <input type="email" name="correo" value="{{ $p['correo'] }}" required maxlength="100" autocomplete="off" /></label>
                  <label>Celular <input type="tel" name="celular" value="{{ $p['celular'] === 'Sin celular' ? '' : $p['celular'] }}" maxlength="20" autocomplete="off" /></label>
                  <label>Especialidad
                    <select name="instrumento"><option value="">Sin cambio</option>@foreach($instrumentos as $i)<option value="{{ $i->id_instrumento }}">{{ $i->nombre }}</option>@endforeach</select>
                  </label>
                  <button class="btn btn--sm" type="submit">Guardar cambios</button>
                </form>
              </details>
              @include('admin.partials.estado-usuario', ['idUsuario' => $p['id_usuario'], 'activo' => $p['activo']])
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="empty-state">
      <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-grupo"/></svg>
      <h3>Todavía no hay profesores</h3>
      <p>Registra al primero con el formulario de abajo. Recibirá una contraseña temporal que puede cambiar desde su perfil.</p>
    </div>
  @endif
</section>

<section class="panel" id="nuevo-profesor" aria-labelledby="nuevo-titulo">
  <div class="section-head">
    <div>
      <h2 id="nuevo-titulo">Registrar profesor</h2>
      <p>Crea su cuenta de acceso. Comunícale la contraseña temporal en persona; nunca se envía por correo.</p>
    </div>
  </div>

  <form action="{{ url('/dashboard-admin/profesores') }}" method="POST" class="teacher-form">
    @csrf
    <div class="field-grid">
      <label>Nombres <input type="text" name="nombres" value="{{ old('nombres') }}" required maxlength="100" autocomplete="off" /></label>
      <label>Apellido paterno <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno') }}" required maxlength="100" autocomplete="off" /></label>
      <label>Apellido materno <input type="text" name="apellido_materno" value="{{ old('apellido_materno') }}" maxlength="100" autocomplete="off" /></label>
      <label>Correo <input type="email" name="correo" value="{{ old('correo') }}" required maxlength="100" autocomplete="off" /></label>
      <label>Carnet de identidad <input type="text" name="ci" value="{{ old('ci') }}" maxlength="20" autocomplete="off" /></label>
      <label>Celular <input type="tel" name="celular" value="{{ old('celular') }}" maxlength="20" autocomplete="off" /></label>
      <label>Especialidad
        <select name="especialidad">
          <option value="">Sin especialidad</option>
          @foreach($instrumentos as $i)
            <option value="{{ $i->nombre }}" @selected(old('especialidad') === $i->nombre)>{{ $i->nombre === 'Violin' ? 'Violín' : $i->nombre }}</option>
          @endforeach
        </select>
      </label>
      <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" /></label>
      <label class="wide">Dirección <input type="text" name="direccion" value="{{ old('direccion') }}" maxlength="500" autocomplete="off" /></label>
      <label>Contraseña temporal
        <input type="password" name="contrasena" required minlength="8" autocomplete="new-password" aria-describedby="ayuda-clave" />
        <span class="campo__ayuda" id="ayuda-clave">Mínimo 8 caracteres, con letras y números.</span>
      </label>
      <label>Confirmar contraseña <input type="password" name="contrasena_confirmation" required minlength="8" autocomplete="new-password" /></label>
    </div>
    <div class="form-actions">
      <button type="reset" class="btn btn--sec">Limpiar</button>
      <button type="submit" class="btn">Registrar profesor</button>
    </div>
  </form>
</section>
@endsection
