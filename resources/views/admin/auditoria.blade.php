@extends('layouts.admin', ['activo' => 'auditoria'])
@section('titulo', 'Bitácora de seguridad')
@section('eyebrow', 'Dirección · Institución')

@section('contenido')
<section class="panel">
  <div class="section-head">
    <div><h2>Eventos registrados</h2><p>Nunca se guardan contraseñas, códigos ni tokens. Los correos de intentos fallidos se muestran enmascarados.</p></div>
    <form method="GET" class="filtro-form">
      <label class="sr-only" for="accion">Filtrar por acción</label>
      <select name="accion" id="accion">
        <option value="">Todas las acciones</option>
        @foreach($acciones as $a)<option value="{{ $a }}" @selected($accion === $a)>{{ \App\Services\AdminDashboardService::nombreAccion($a) }}</option>@endforeach
      </select>
      <button class="btn-msea small secondary" type="submit">Filtrar</button>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Fecha</th><th>Acción</th><th>Usuario</th><th>Detalle</th><th>IP</th></tr></thead>
      <tbody>
      @forelse($registros as $r)
        <tr>
          <td>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i:s') }}</td>
          <td><span class="pill">{{ \App\Services\AdminDashboardService::nombreAccion($r->accion) }}</span></td>
          <td>{{ $r->nombres ? trim($r->nombres.' '.$r->apellido_paterno) : '-' }}</td>
          <td><small class="detalle-json">{{ $r->detalle }}</small></td>
          <td>{{ $r->ip }}</td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-escudo"/></svg><strong>No hay eventos registrados.</strong></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="paginacion">{{ $registros->links('pagination::simple-default') }}</div>
</section>
@endsection
