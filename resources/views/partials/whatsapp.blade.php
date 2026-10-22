{{-- Boton flotante de WhatsApp. Solo aparece si la fundacion configura MSEA_WHATSAPP. --}}
@php($wa = config('msea.sitio.whatsapp'))
@if($wa)
  @once<link rel="stylesheet" href="{{ asset('css/publico-comun.css') }}" />@endonce
  <aside aria-label="Contacto por WhatsApp">
  <a class="boton-whatsapp" href="https://wa.me/{{ $wa }}?text={{ rawurlencode(config('msea.sitio.whatsapp_mensaje')) }}"
     target="_blank" rel="noopener noreferrer" aria-label="Escribir por WhatsApp (se abre en una pestaña nueva)">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
    <span>WhatsApp</span>
  </a>
  </aside>
@endif
