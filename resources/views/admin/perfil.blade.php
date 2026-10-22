@extends('layouts.admin', ['activo' => 'perfil'])
@section('titulo', 'Mi perfil')
@section('eyebrow', 'Dirección · Institución')

@section('contenido')
@php($perfil = $directorProfile)
<div class="perfil-grid">
  <section class="panel" aria-labelledby="perfil-titulo">
    <div class="section-head">
      <div>
        <h2 id="perfil-titulo">Datos del director</h2>
        <p>Tu correo recibe los códigos de recuperación. Para cambiarlo, confirma tu contraseña actual.</p>
      </div>
    </div>

    <form action="{{ url('/dashboard-admin/perfil') }}" method="POST" class="teacher-form">
      @csrf
      <div class="field-grid">
        <label>Nombres <input type="text" name="nombres" value="{{ old('nombres', $perfil['nombres']) }}" required maxlength="100" autocomplete="given-name" /></label>
        <label>Apellido paterno <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno', $perfil['apellido_paterno']) }}" maxlength="100" autocomplete="family-name" /></label>
        <label>Apellido materno <input type="text" name="apellido_materno" value="{{ old('apellido_materno', $perfil['apellido_materno']) }}" maxlength="100" /></label>
        <label>Correo <input type="email" name="correo" value="{{ old('correo', $perfil['correo']) }}" required maxlength="100" autocomplete="email" /></label>
        <label>Carnet de identidad <input type="text" name="ci" value="{{ old('ci', $perfil['ci']) }}" maxlength="20" /></label>
        <label>Celular <input type="tel" name="celular" value="{{ old('celular', $perfil['celular']) }}" maxlength="20" autocomplete="tel" /></label>
        <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $perfil['fecha_nacimiento']) }}" autocomplete="bday" /></label>
        <label class="wide">Dirección <input type="text" name="direccion" value="{{ old('direccion', $perfil['direccion']) }}" maxlength="500" autocomplete="street-address" /></label>
        <label class="wide">Trayectoria
          <textarea name="trayectoria" rows="5" maxlength="2000">{{ old('trayectoria', $perfil['trayectoria']) }}</textarea>
        </label>
        <label class="wide">Contraseña actual
          <input type="password" name="contrasena_actual" autocomplete="current-password" aria-describedby="ayuda-actual" />
          <span class="campo__ayuda" id="ayuda-actual">Solo hace falta si cambias el correo.</span>
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn">Guardar perfil</button>
      </div>
    </form>
  </section>

  <aside class="perfil-lateral">
    <section class="panel" aria-labelledby="cuentas-titulo">
      <h2 id="cuentas-titulo" class="perfil-lateral__titulo">Cuentas en MSEA</h2>
      <dl class="cifras cifras--lista">
        @forelse($cuentasPorRol as $rol)
          <div><dt>{{ $rol['label'] }}</dt><dd>{{ $rol['value'] }}</dd></div>
        @empty
          <div><dt>Cuentas</dt><dd>0</dd></div>
        @endforelse
      </dl>
    </section>

    <section class="panel" aria-labelledby="guia-titulo">
      <h2 id="guia-titulo" class="perfil-lateral__titulo">Guía rápida</h2>
      <ul class="guia">
        @foreach($ayudaData as $ayuda)
          <li><strong>{{ $ayuda['titulo'] }}</strong><span>{{ $ayuda['detalle'] }}</span></li>
        @endforeach
      </ul>
    </section>
  </aside>
</div>
@endsection
