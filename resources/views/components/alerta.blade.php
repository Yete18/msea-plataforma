{{--
  Alerta con color, icono y rol segun el tipo (UX-03).
  <x-alerta tipo="error">Texto</x-alerta>   tipos: exito, error, aviso, info
  Los errores y avisos se anuncian de inmediato (role="alert"); el resto, con cortesia (role="status").
--}}
@props(['tipo' => 'info', 'cerrable' => false])
@php
  $icono = ['exito' => 'check-circulo', 'error' => 'alerta', 'aviso' => 'alerta', 'info' => 'info'][$tipo] ?? 'info';
  $rol = in_array($tipo, ['error', 'aviso'], true) ? 'alert' : 'status';
@endphp
<div {{ $attributes->merge(['class' => 'alerta alerta--'.$tipo, 'role' => $rol]) }}>
  <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-{{ $icono }}"/></svg>
  <div class="alerta__texto">{{ $slot }}</div>
  @if($cerrable)
    <button type="button" class="alerta__cerrar" data-cerrar-alerta aria-label="Cerrar aviso"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-cerrar"/></svg></button>
  @endif
</div>
