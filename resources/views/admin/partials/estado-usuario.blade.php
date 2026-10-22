<form action="{{ url('/dashboard-admin/usuarios/'.$idUsuario.'/estado') }}" method="POST" class="inline-action">
  @csrf
  <input type="hidden" name="activo" value="{{ $activo ? 0 : 1 }}" />
  <button type="submit" class="btn-msea small {{ $activo ? 'danger' : 'secondary' }}" data-confirmar="{{ $activo ? '¿Desactivar esta cuenta? Perderá el acceso, pero su historial se conserva.' : '¿Reactivar esta cuenta?' }}">
    {{ $activo ? 'Desactivar' : 'Reactivar' }}
  </button>
</form>
<form action="{{ url('/dashboard-admin/usuarios/'.$idUsuario.'/recuperacion') }}" method="POST" class="inline-action">
  @csrf
  <button type="submit" class="btn-msea small secondary" data-confirmar="¿Enviar un código de recuperación al correo de esta cuenta?">Enviar recuperación</button>
</form>
