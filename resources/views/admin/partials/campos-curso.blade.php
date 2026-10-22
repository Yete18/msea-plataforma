<div class="field-grid">
  <label class="wide"><span>Título</span><input name="titulo" value="{{ old('titulo', $curso?->titulo) }}" required maxlength="150" /></label>
  <label><span>Instrumento</span>
    <select name="instrumento">
      <option value="">Todos</option>
      @foreach($instrumentos as $i)<option @selected(old('instrumento', $curso?->instrumento) === $i)>{{ $i }}</option>@endforeach
    </select>
  </label>
  <label><span>Nivel formativo</span>
    <select name="nivel">@foreach($niveles as $n)<option @selected(old('nivel', $curso?->nivel) === $n)>{{ $n }}</option>@endforeach</select>
  </label>
  <label><span>Profesor responsable</span>
    <select name="id_profesor">
      <option value="">Sin asignar</option>
      @foreach($profesores as $p)<option value="{{ $p->id_profesor }}" @selected((int) old('id_profesor', $curso?->id_profesor) === (int) $p->id_profesor)>{{ $p->nombres }} {{ $p->apellido_paterno }}</option>@endforeach
    </select>
  </label>
  <label class="inline"><input type="hidden" name="activo" value="0" /><input type="checkbox" name="activo" value="1" @checked(old('activo', $curso?->activo ?? true)) /> Curso activo (visible para inscritos)</label>
  <label class="wide"><span>Descripción</span><textarea name="descripcion" rows="3" maxlength="2000">{{ old('descripcion', $curso?->descripcion) }}</textarea></label>
</div>
