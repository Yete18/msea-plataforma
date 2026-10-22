{{-- Campos de una escala de la biblioteca ($e = resumen de la escala o null). --}}
@php
  $tonicas = ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'];
  $sufijo = $e ? 'e'.$e['id'] : 'nueva';
  $instClave = $e['instrumento'] ?? ($instrumento ?? 'violin');
  $minimo = \App\Services\EscalaService::INSTRUMENTOS[$instClave]['min'];
  $octavaBaja = intdiv($minimo, 12) - 1;
  $inicio = $e['inicio'] ?? ['1' => $octavaBaja + 1];
@endphp
<div class="field-grid escala-campos">
  <label>Tónica
    <select name="tonica" required>
      @foreach($tonicas as $letra => $nombre)
        <option value="{{ $letra }}" @selected(old('tonica', $e['tonica'] ?? 'A') === $letra)>{{ $nombre }}</option>
      @endforeach
    </select>
  </label>
  <label>Alteración
    <select name="alteracion" required>
      <option value="0" @selected((int) old('alteracion', $e['alteracion'] ?? 0) === 0)>Natural</option>
      <option value="1" @selected((int) old('alteracion', $e['alteracion'] ?? 0) === 1)>Sostenido (♯)</option>
      <option value="-1" @selected((int) old('alteracion', $e['alteracion'] ?? 0) === -1)>Bemol (♭)</option>
    </select>
  </label>
  <label>Modo
    <select name="modo" required>
      @foreach($modos as $clave => $nombre)
        <option value="{{ $clave }}" @selected(old('modo', $e['modo'] ?? 'mayor') === $clave)>{{ ucfirst($nombre) }}</option>
      @endforeach
    </select>
  </label>
  <label>Dificultad
    <select name="dificultad" required>
      @foreach($dificultades as $clave => $nombre)
        <option value="{{ $clave }}" @selected(old('dificultad', $e['dificultad'] ?? 'facil') === $clave)>{{ $nombre }}</option>
      @endforeach
    </select>
  </label>
  @foreach([1, 2, 3] as $n)
    <label>Octava de la tónica al tocar {{ $n }} {{ $n === 1 ? 'octava' : 'octavas' }}
      <select name="inicio[{{ $n }}]" @if($n === 1) required @endif>
        @if($n > 1)<option value="">No disponible</option>@endif
        @foreach(range($octavaBaja, $octavaBaja + 2) as $oct)
          <option value="{{ $oct }}" @selected((string) ($inicio[(string) $n] ?? '') === (string) $oct)>{{ $oct }}{{ $oct === 4 ? ' (La4 = 440 Hz)' : '' }}</option>
        @endforeach
      </select>
    </label>
  @endforeach
  <label>Nombre <span class="hint">Opcional. Si lo dejas vacío: «La mayor», «Re menor armónica»...</span>
    <input name="nombre" maxlength="120" value="{{ old('nombre', $e['nombre'] ?? '') }}" />
  </label>
  <label>Orden <input type="number" name="orden" min="0" max="9999" value="{{ old('orden', $e['orden'] ?? 0) }}" /></label>
  <label class="wide">Descripción para el profesor <textarea name="descripcion" maxlength="1000" rows="2">{{ old('descripcion', $e['descripcion'] ?? '') }}</textarea></label>
</div>
