@php($opciones = array_pad((array) ($pregunta?->opciones ?? []), 4, ''))
<label><span>Título</span><input name="titulo" value="{{ $pregunta?->titulo }}" required maxlength="150" /></label>
<label><span>Pregunta</span><textarea name="pregunta" required maxlength="1000">{{ $pregunta?->pregunta }}</textarea></label>
<div class="opciones-grid">
  @foreach($opciones as $i => $op)
    <label><span>Opción {{ $i + 1 }}</span><input name="opciones[]" value="{{ $op }}" maxlength="150" @if($i < 2) required @endif /></label>
  @endforeach
</div>
<label><span>Respuesta correcta (escríbela igual que la opción)</span><input name="respuesta_correcta" value="{{ $pregunta?->respuesta_correcta }}" required maxlength="150" /></label>
<label><span>XP</span><input type="number" name="xp" value="{{ $pregunta?->xp ?? 10 }}" min="0" max="100" /></label>
<label class="inline"><input type="hidden" name="activo" value="0" /><input type="checkbox" name="activo" value="1" @checked($pregunta?->activo ?? true) /> Activa</label>
