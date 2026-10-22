<label><span>Título</span><input name="titulo" value="{{ $leccion?->titulo }}" required maxlength="150" /></label>
<label><span>Tipo</span>
  <select name="tipo">@foreach(['teoria' => 'Teoría', 'tecnica' => 'Técnica', 'ritmo' => 'Ritmo', 'afinacion' => 'Afinación', 'repertorio' => 'Repertorio'] as $v => $t)<option value="{{ $v }}" @selected(($leccion?->tipo ?? 'teoria') === $v)>{{ $t }}</option>@endforeach</select>
</label>
<label><span>Orden</span><input type="number" name="orden" value="{{ $leccion?->orden }}" min="1" max="999" /></label>
<label><span>XP al completar</span><input type="number" name="xp" value="{{ $leccion?->xp ?? 20 }}" min="0" max="200" /></label>
<label><span>Contenido</span><textarea name="contenido" rows="5" maxlength="10000">{{ $leccion?->contenido }}</textarea></label>
<label class="inline"><input type="hidden" name="publicado" value="0" /><input type="checkbox" name="publicado" value="1" @checked($leccion?->publicado ?? true) /> Publicada</label>
