/* Boton "Continuar con Google" en /login (Google Identity Services).
   Google devuelve un ID token firmado; el servidor lo verifica y abre la sesion. */
(function () {
  'use strict';

  const caja = document.getElementById('login-google');
  if (!caja) return;
  const alerta = document.getElementById('google-alert');

  function mostrarError(texto) {
    if (!alerta) return;
    alerta.textContent = texto;
    alerta.hidden = false;
  }

  async function alRecibirCredencial(respuesta) {
    if (alerta) alerta.hidden = true;
    try {
      const data = await window.MSEA_UI.api('/auth/google', { json: { credential: respuesta.credential } });
      window.location.href = data.redirect || '/';
    } catch (err) {
      mostrarError(err.message || 'No se pudo iniciar sesión con Google.');
    }
  }

  // Google llama a esta funcion cuando su script termina de cargar.
  window.onGoogleLibraryLoad = function () {
    try {
      window.google.accounts.id.initialize({
        client_id: caja.dataset.clientId,
        callback: alRecibirCredencial,
        ux_mode: 'popup',
        auto_select: false,
        cancel_on_tap_outside: true,
      });
      const ancho = Math.min(380, caja.clientWidth || 320);
      window.google.accounts.id.renderButton(document.getElementById('google-boton'), {
        type: 'standard', theme: 'outline', size: 'large', text: 'continue_with',
        shape: 'pill', logo_alignment: 'left', locale: 'es', width: ancho,
      });
    } catch (e) {
      mostrarError('No se pudo cargar el botón de Google.');
    }
  };

  // Si el script de Google no carga (sin internet o bloqueado), se avisa.
  setTimeout(() => {
    if (!window.google || !window.google.accounts) {
      mostrarError('No se pudo conectar con Google. Puedes ingresar con tu correo y contraseña.');
    }
  }, 8000);
})();
