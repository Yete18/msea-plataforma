/* ============================================================
   MSEA — JAVASCRIPT LOGIN
   Funciones: validación accesible, mostrar contraseña,
              y estado de carga al enviar
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initPasswordToggle();
  initFormValidation();
  initPanelImgFallback();
});

/* ============================================================
   3. TOGGLE MOSTRAR / OCULTAR CONTRASEÑA
   ============================================================ */
function initPasswordToggle() {
  const toggle   = document.getElementById('toggle-password');
  const passInput = document.getElementById('contrasena');
  if (!toggle || !passInput) return;

  let visible = false;

  toggle.addEventListener('click', () => {
    visible = !visible;
    passInput.type = visible ? 'text' : 'password';
    toggle.innerHTML = window.MSEA_UI.icon(visible ? 'ocultar' : 'ver');
    toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    toggle.setAttribute('aria-pressed', String(visible));
  });
}

/* ============================================================
   4. VALIDACIÓN DEL FORMULARIO
   Valida al salir del campo y al enviar; el envío lo procesa el servidor.
   ============================================================ */
function initFormValidation() {
  const form        = document.getElementById('login-form');
  const correoInput  = document.getElementById('correo');
  const passInput    = document.getElementById('contrasena');
  const btnLogin     = document.getElementById('btn-login');
  const btnText      = btnLogin?.querySelector('.btn-text');
  const btnLoader    = document.getElementById('btn-loader');

  if (!form) return;

  // ── Validación en tiempo real al salir del campo ──
  correoInput?.addEventListener('blur', () => validateField(correoInput, 'correo'));
  passInput?.addEventListener('blur',    () => validateField(passInput, 'password'));

  // ── Limpiar error al escribir ──
  correoInput?.addEventListener('input', () => clearFieldError(correoInput, 'correo'));
  passInput?.addEventListener('input',    () => clearFieldError(passInput, 'password'));

  // ── Envío del formulario ──
  form.addEventListener('submit', async (e) => {
    //e.preventDefault();

    // Validar todos los campos
    const okCorreo  = validateField(correoInput, 'correo');
    const okPass    = validateField(passInput, 'password');

    if (!okCorreo || !okPass) {
      e.preventDefault();
      window.MSEA_CAMPOS?.enfocarPrimero(form);
      return;
    }

    // Evita el doble envío mientras el servidor responde (FE-07).
    setLoading(true);
  });

  // Si el navegador restaura la página (flecha atrás), el botón vuelve a estar activo.
  window.addEventListener('pageshow', () => setLoading(false));

  // ── Helpers ──

  function validateField(input, name) {
    const val = input.value.trim();
    let error = '';

    if (name === 'correo') {
      if (!val) error = 'Ingresa tu correo electrónico';
      else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) error = 'Ingresa un correo electrónico válido';
    }

    if (name === 'password') {
      if (!val) error = 'Ingresa tu contraseña';
      else if (val.length < 4) error = 'La contraseña es demasiado corta';
    }

    showFieldError(input, name, error);
    return !error;
  }

  function showFieldError(input, name, message) {
    const errorEl   = document.getElementById(`error-${name}`);
    const statusEl  = document.getElementById(`status-${name}`);

    window.MSEA_CAMPOS?.marcar(input, message);
    if (message) {
      input.classList.add('invalid');
      input.classList.remove('valid');
      if (errorEl)  errorEl.textContent = message;
      if (statusEl) statusEl.innerHTML = window.MSEA_UI.icon('cerrar', 'icon-red');
    } else {
      input.classList.remove('invalid');
      input.classList.add('valid');
      if (errorEl)  errorEl.textContent = '';
      if (statusEl) statusEl.innerHTML = window.MSEA_UI.icon('check', 'icon-green');
    }
  }

  function clearFieldError(input, name) {
    if (input.classList.contains('invalid')) {
      input.classList.remove('invalid');
      input.removeAttribute('aria-invalid');
      const errorEl = document.getElementById(`error-${name}`);
      if (errorEl) errorEl.textContent = '';
    }
  }

  function setLoading(state) {
    if (!btnLogin || !btnText || !btnLoader) return;
    btnLogin.disabled = state;
    btnLogin.setAttribute('aria-busy', String(state));
    btnText.hidden = state;
    btnLoader.hidden = !state;
  }


}

/* ============================================================
   5. FALLBACK IMAGEN DEL PANEL
   ============================================================ */
function initPanelImgFallback() {
  const img = document.querySelector('.panel-img');
  if (!img) return;
  img.addEventListener('error', () => {
    img.classList.add('error');
  });
}
