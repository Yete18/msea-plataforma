@extends('layouts.admin', ['activo' => 'comunicados'])
@section('titulo', 'Comunicados institucionales')
@section('eyebrow', 'Dirección · Institución')

@section('contenido')
<section class="panel">
  <div class="section-head"><div><h2>Nuevo comunicado</h2><p>Llega como notificación a cada usuario activo del grupo elegido (web y app).</p></div></div>
  <form action="{{ url('/dashboard-admin/comunicados') }}" method="POST" class="teacher-form">
    @csrf
    <div class="field-grid">
      <label class="wide"><span>Título</span><input name="titulo" value="{{ old('titulo') }}" required maxlength="150" /></label>
      <label class="wide"><span>Mensaje</span><textarea name="mensaje" rows="5" required maxlength="2000">{{ old('mensaje') }}</textarea></label>
      <fieldset class="wide destinatarios">
        <legend>Destinatarios</legend>
        @foreach(['estudiante' => 'Estudiantes', 'padre' => 'Padres y tutores', 'profesor' => 'Profesores', 'director' => 'Dirección'] as $rol => $texto)
          <label class="inline"><input type="checkbox" name="roles[]" value="{{ $rol }}" @checked(in_array($rol, old('roles', ['estudiante', 'padre']), true)) /> {{ $texto }}</label>
        @endforeach
      </fieldset>
      <label><span>O solo a un elenco</span>
        <select name="id_elenco"><option value="">Todos los roles marcados</option>@foreach($elencos as $e)<option value="{{ $e->id_elenco }}">{{ $e->nombre }}</option>@endforeach</select>
      </label>
    </div>
    <div class="form-actions"><button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Enviar comunicado</button></div>
  </form>
</section>

<section class="panel">
  <div class="section-head"><div><h2>Enviados recientemente</h2></div></div>
  <ul class="lista-auditoria">
    @forelse($recientes as $r)
      @php($d = json_decode($r->detalle ?? '{}', true) ?: [])
      <li><strong>{{ $d['destinatarios'] ?? 0 }} destinatario(s)</strong> <small>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</small></li>
    @empty
      <li><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-campana"/></svg><strong>Aún no se enviaron comunicados.</strong></div></li>
    @endforelse
  </ul>
</section>
@endsection
