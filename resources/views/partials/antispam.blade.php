{{-- Proteccion contra robots: campo trampa oculto + hora firmada (ver ProteccionSpam). --}}
<div class="campo-trampa" aria-hidden="true">
  <label for="sitio_web_{{ $idForm ?? 'f' }}">Deja este campo vacío</label>
  <input type="text" id="sitio_web_{{ $idForm ?? 'f' }}" name="{{ \App\Http\Middleware\ProteccionSpam::CAMPO_TRAMPA }}" value="" tabindex="-1" autocomplete="off" />
</div>
<input type="hidden" name="{{ \App\Http\Middleware\ProteccionSpam::CAMPO_HORA }}" value="{{ \App\Http\Middleware\ProteccionSpam::horaFirmada() }}" />
