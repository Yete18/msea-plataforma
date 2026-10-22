{{-- Tema claro u oscuro antes de pintar la página (FE-06): sin destello blanco.
     Usa la elección guardada en este navegador o, si no hay, la del sistema.
     En línea con el nonce de la CSP; theme-toggle.js se encarga del botón. --}}
<script nonce="{{ $cspNonce ?? '' }}">(function(){var t=null;try{t=localStorage.getItem('msea-theme');}catch(e){}var d=t?t==='dark':!!(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);if(d)document.documentElement.classList.add('theme-dark');})();</script>
