@extends('layouts.admin', ['activo' => 'digitalizador'])
@section('titulo', 'Digitalizador de métodos')
@section('eyebrow', 'Dirección · Enseñanza')

@section('contenido')
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Subir un método en PDF</h2>
      <p>Sube el libro completo y elige solo las páginas que quieres digitalizar (por ejemplo <strong>4, 6</strong> o <strong>4-6</strong>). Cada página se reconoce, la revisas junto a la imagen original y lo que apruebes pasa a la biblioteca para todos los profesores.</p>
    </div>
  </div>

  <p class="estado-motor {{ $motor ? 'ok' : 'aviso' }}">
    <svg class="icon"><use href="{{ $iconos }}#i-{{ $motor ? 'check-circulo' : 'info' }}"/></svg>
    @if($motor)
      Reconocimiento automático activo (Audiveris). Aun así, revisa cada ejercicio antes de publicarlo: ningún reconocimiento es perfecto.
    @else
      El reconocimiento automático no está configurado. Puedes digitalizar igual escribiendo las notas mientras miras la página.
      Para activarlo, instala Audiveris y pon su ruta en <code>MSEA_OMR_AUDIVERIS</code> del archivo .env.
    @endif
  </p>

  <form action="{{ url('/dashboard-admin/digitalizador') }}" method="POST" enctype="multipart/form-data" class="teacher-form">
    @csrf
    <div class="field-grid">
      <label class="wide"><span>PDF del método (máximo {{ $maxMb }} MB)</span><input type="file" name="pdf" accept="application/pdf,.pdf" required /></label>
      <label><span>Título</span><input name="titulo" required maxlength="150" value="{{ old('titulo') }}" placeholder="Ševčík Op. 1, libro 1" /></label>
      <label><span>Método o autor (opcional)</span><input name="metodo" maxlength="150" value="{{ old('metodo') }}" placeholder="Ševčík Op. 1" /></label>
      <label><span>Instrumento</span>
        <select name="instrumento" required>
          @foreach($instrumentos as $clave => $nombre)<option value="{{ $clave }}" @selected(old('instrumento', 'violin') === $clave)>{{ $nombre }}</option>@endforeach
        </select>
      </label>
      <label><span>Páginas a digitalizar</span><input name="paginas" required maxlength="200" value="{{ old('paginas') }}" placeholder="4, 6" /></label>
      <label class="wide inline"><input type="checkbox" name="derechos" value="1" required @checked(old('derechos')) /> El método es de dominio público o la fundación tiene permiso para usarlo.</label>
    </div>
    <div class="form-actions"><button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-subir"/></svg> Subir y digitalizar</button></div>
    <p class="hint">Con el reconocimiento automático, cada página tarda alrededor de 10 a 60 segundos.</p>
  </form>
</section>

<section class="panel">
  <div class="section-head"><div><h2>Digitalizaciones</h2></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Título</th><th>Instrumento</th><th>Páginas</th><th>Estado</th><th>Publicados</th><th></th></tr></thead>
      <tbody>
      @forelse($digitalizaciones as $d)
        <tr>
          <td><strong>{{ $d->titulo }}</strong><small>{{ $d->archivo_nombre }}{{ $d->paginas_total ? ' · '.$d->paginas_total.' páginas en total' : '' }}</small></td>
          <td>{{ $instrumentos[$d->instrumento] ?? $d->instrumento }}</td>
          <td>{{ implode(', ', $d->paginas_lista) }}</td>
          <td><span class="estado-tag {{ $d->estado === 'revisar' ? 'entregada' : ($d->estado === 'error' ? 'devuelta' : 'pendiente') }}">{{ ['pendiente' => 'Pendiente', 'procesando' => 'Reconociendo', 'revisar' => 'Para revisar', 'error' => 'Error'][$d->estado] ?? $d->estado }}</span></td>
          <td>{{ $d->publicados }}</td>
          <td><a class="btn-msea small" href="{{ url('/dashboard-admin/digitalizador/'.$d->id_digitalizacion) }}">Revisar</a></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-archivo"/></svg><strong>Todavía no hay métodos digitalizados.</strong></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
