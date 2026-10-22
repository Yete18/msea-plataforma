/* ============================================================
   MSEA — JAVASCRIPT REGISTRO
   Funciones: partículas, pasos, selectores, validación,
              fortaleza de contraseña (el envio es un formulario real)
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initPanelParticles();
  initSteps();
  initInstrumentSelector();
  initLevelSelector();
  initPasswordToggles();
  initPasswordStrength();
  initPanelImgFallback();
});

/* ============================================================
   1. PARTÍCULAS DEL PANEL
   ============================================================ */
function initPanelParticles() {
  const container = document.getElementById('panel-particles');
  if (!container) return;
  for (let i = 0; i < 12; i++) {
    const p = document.createElement('div');
    p.classList.add('panel-particle');
    const size     = Math.random() * 50 + 15;
    const left     = Math.random() * 100;
    const duration = Math.random() * 8 + 6;
    const delay    = Math.random() * 10;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${left}%;
      bottom:-${size}px;
      animation-duration:${duration}s;
      animation-delay:-${delay}s;
    `;
    container.appendChild(p);
  }
}

/* ============================================================
   2. CONTROL DE PASOS (Paso 1 → Paso 2 → Éxito)
   ============================================================ */
function initSteps() {
  const step1     = document.getElementById('step-1');
  const step2     = document.getElementById('step-2');
  const stepOk    = document.getElementById('step-success');
  const btnNext1  = document.getElementById('btn-next-1');
  const btnBack2  = document.getElementById('btn-back-2');
  const btnSubmit = document.getElementById('btn-submit');
  const form      = document.getElementById('registro-form');
  const progress  = document.getElementById('progress-fill');
  const pstep1    = document.getElementById('pstep-1');
  const pstep2    = document.getElementById('pstep-2');
  const formTitle    = document.getElementById('form-title');
  const formSubtitle = document.getElementById('form-subtitle');
  const formFooter   = document.getElementById('form-footer');

  // ── Ir a paso 2 ──
  btnNext1?.addEventListener('click', () => {
    if (!validateStep1()) { window.MSEA_CAMPOS?.enfocarPrimero(step1); return; }
    setStep(2);
    step2?.querySelector('.instrument-btn')?.focus();
  });

  // ── Volver a paso 1 ──
  btnBack2?.addEventListener('click', () => { setStep(1); document.getElementById('nombre')?.focus(); });

  // ── Enviar registro ──
  form?.addEventListener('submit', (event) => {
    if (!validateStep2()) {
      event.preventDefault();
      window.MSEA_CAMPOS?.enfocarPrimero(step2);
      return;
    }

    const btnText = btnSubmit?.querySelector('.btn-text');
    const btnLoader = document.getElementById('btn-loader');

    if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.setAttribute('aria-busy', 'true'); }
    if (btnText) btnText.hidden = true;
    if (btnLoader) btnLoader.hidden = false;
  });

  // Si el servidor devolvió errores solo del paso 2, se abre ese paso (UX-04).
  try {
    const errores = JSON.parse(document.getElementById('msea-errores-campos')?.textContent || '{}');
    const delPaso1 = ['nombres', 'apellido_paterno', 'correo'].some((k) => k in errores);
    // Instrumento y nivel son campos ocultos: el error se asocia a su grupo de botones.
    [['instrumento', 'instrument-selector'], ['nivel', 'level-selector']].forEach(([campo, grupo]) => {
      if (errores[campo]) window.MSEA_CAMPOS?.marcar(document.getElementById(grupo), [].concat(errores[campo])[0]);
    });
    if (!delPaso1 && Object.keys(errores).length) setTimeout(() => {
      setStep(2);
      window.MSEA_CAMPOS?.enfocarPrimero(step2);
    }, 0);
  } catch (e) { /* sin errores del servidor */ }

  // ── Cambiar paso visualmente ──
  function setStep(num) {
    // Ocultar todos los pasos
    [step1, step2, stepOk].forEach(s => s?.classList.remove('active'));

    if (num === 1) {
      step1?.classList.add('active');
      progress.style.width = '50%';
      pstep1?.classList.add('active');
      pstep2?.classList.remove('active');
      formTitle.textContent    = 'Crear cuenta';
      formSubtitle.textContent = 'Paso 1 de 2 — Cuéntanos quién eres';
      formFooter.style.display = '';
    } else if (num === 2) {
      step2?.classList.add('active');
      progress.style.width = '100%';
      pstep1?.classList.remove('active');
      pstep2?.classList.add('active');
      formTitle.textContent    = 'Tu instrumento';
      formSubtitle.textContent = 'Paso 2 de 2: ya casi terminas';
      formFooter.style.display = '';
    } else if (num === 'success') {
      stepOk?.classList.add('active');
      progress.style.width = '100%';
      formTitle.textContent    = '¡Listo!';
      formSubtitle.textContent = 'Registro completado con éxito';
      formFooter.style.display = 'none';
    }
  }
}

/* ============================================================
   3. SELECTOR DE INSTRUMENTO
   ============================================================ */
function initInstrumentSelector() {
  const btns  = document.querySelectorAll('.instrument-btn');
  const input = document.getElementById('instrumento');
  if (!btns.length || !input) return;

  // Estado accesible y valor que devolvió el servidor tras un error (UX-04).
  btns.forEach(b => {
    const elegido = b.dataset.value === input.value && input.value !== '';
    b.classList.toggle('active', elegido);
    b.setAttribute('aria-pressed', String(elegido));
  });

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');
      input.value = btn.dataset.value;
      // Limpiar error
      window.MSEA_CAMPOS?.marcar(document.getElementById('instrument-selector'), '');
      document.getElementById('error-instrumento').textContent = '';
      // Animación rápida
      btn.style.transform = 'scale(0.9)';
      setTimeout(() => btn.style.transform = '', 150);
    });
  });
}

/* ============================================================
   4. SELECTOR DE NIVEL
   ============================================================ */
function initLevelSelector() {
  const btns  = document.querySelectorAll('.level-btn');
  const input = document.getElementById('nivel');
  if (!btns.length || !input) return;

  // Estado accesible y valor que devolvió el servidor tras un error (UX-04).
  btns.forEach(b => {
    const elegido = b.dataset.value === input.value && input.value !== '';
    b.classList.toggle('active', elegido);
    b.setAttribute('aria-pressed', String(elegido));
  });

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');
      input.value = btn.dataset.value;
      window.MSEA_CAMPOS?.marcar(document.getElementById('level-selector'), '');
      document.getElementById('error-nivel').textContent = '';
      btn.style.transform = 'scale(0.9)';
      setTimeout(() => btn.style.transform = '', 150);
    });
  });
}

/* ============================================================
   5. TOGGLE MOSTRAR / OCULTAR CONTRASEÑAS
   ============================================================ */
function initPasswordToggles() {
  setupToggle('toggle-pass1', 'password');
  setupToggle('toggle-pass2', 'confirm-password');

  function setupToggle(toggleId, inputId) {
    const toggle = document.getElementById(toggleId);
    const input  = document.getElementById(inputId);
    if (!toggle || !input) return;
    let visible = false;
    toggle.addEventListener('click', () => {
      visible = !visible;
      input.type     = visible ? 'text' : 'password';
      toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
      toggle.classList.toggle('visible', visible);
    });
  }
}

/* ============================================================
   6. BARRA DE FORTALEZA DE CONTRASEÑA
   Muestra qué tan segura es la contraseña en tiempo real
   ============================================================ */
function initPasswordStrength() {
  const passInput = document.getElementById('password');
  const fill      = document.getElementById('strength-fill');
  const label     = document.getElementById('strength-label');
  if (!passInput || !fill || !label) return;

  passInput.addEventListener('input', () => {
    const val   = passInput.value;
    const score = getStrengthScore(val);

    // Colores en auth.css (data-nivel), así funcionan también en modo oscuro.
    const textos = ['', 'Muy débil', 'Débil', 'Aceptable', 'Contraseña segura'];
    fill.dataset.nivel  = String(score);
    label.dataset.nivel = String(score);
    label.textContent   = textos[score];
  });

  // Calcula score 0-4 según criterios
  function getStrengthScore(pass) {
    if (!pass) return 0;
    let score = 0;
    if (pass.length >= 8)                  score++;
    if (pass.length >= 10)                 score++;
    if (/[A-Z]/.test(pass))               score++;
    if (/[0-9!@#$%^&*]/.test(pass))       score++;
    return Math.min(score, 4);
  }
}

/* ============================================================
   7. VALIDACIÓN PASO 1
   ============================================================ */
function validateStep1() {
  const nombre   = document.getElementById('nombre');
  const apellido = document.getElementById('apellido');
  const email    = document.getElementById('email');

  let ok = true;

  ok = validateField(nombre,   'nombre',   v => {
    if (!v)        return 'Ingresa tu nombre';
    if (v.length < 2) return 'Nombre demasiado corto';
    return '';
  }) && ok;

  ok = validateField(apellido, 'apellido', v => {
    if (!v)        return 'Ingresa tu apellido';
    if (v.length < 2) return 'Apellido demasiado corto';
    return '';
  }) && ok;


  ok = validateField(email, 'email', v => {
    if (!v)                        return 'Ingresa tu correo';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Correo no válido';
    return '';
  }) && ok;

  return ok;
}

/* ============================================================
   8. VALIDACIÓN PASO 2
   ============================================================ */
function validateStep2() {
  const instrumento = document.getElementById('instrumento');
  const nivel       = document.getElementById('nivel');
  const password    = document.getElementById('password');
  const confirm     = document.getElementById('confirm-password');

  let ok = true;

  const acepta = document.getElementById('acepta-privacidad');
  const errorPriv = document.getElementById('error-privacidad');
  const C = window.MSEA_CAMPOS;
  const marcar = (el, caja, texto) => {
    if (C) C.marcar(el, texto);
    else if (caja) caja.textContent = texto;
  };

  const grupoInstrumento = document.getElementById('instrument-selector');
  if (!instrumento.value) {
    marcar(grupoInstrumento, document.getElementById('error-instrumento'), 'Elige tu instrumento');
    ok = false;
  }

  const hayNiveles = document.querySelectorAll('#level-selector .level-btn').length > 0;
  if (hayNiveles && !nivel.value) {
    marcar(document.getElementById('level-selector'), document.getElementById('error-nivel'), 'Elige tu nivel');
    ok = false;
  }

  ok = validateField(password, 'password', v => {
    if (!v)        return 'Ingresa una contraseña';
    if (v.length < 8) return 'Mínimo 8 caracteres';
    if (!/[A-Za-z]/.test(v) || !/[0-9]/.test(v)) return 'Incluye letras y números';
    return '';
  }) && ok;

  ok = validateField(confirm, 'confirm', v => {
    if (!v)                              return 'Confirma tu contraseña';
    if (v !== password.value)            return 'Las contraseñas no coinciden';
    return '';
  }) && ok;

  if (acepta && !acepta.checked) {
    marcar(acepta, errorPriv, 'Acepta la política de privacidad para continuar');
    ok = false;
  } else if (acepta) {
    marcar(acepta, errorPriv, '');
  }

  return ok;
}

/* ============================================================
   9. HELPER GENÉRICO DE VALIDACIÓN DE CAMPO
   ============================================================ */
function validateField(input, name, ruleFn) {
  const val     = input.value.trim();
  const error   = ruleFn(val);
  const errorEl = document.getElementById(`error-${name}`);
  const statusEl = document.getElementById(`status-${name}`);
  window.MSEA_CAMPOS?.marcar(input, error);

  if (error) {
    input.classList.add('invalid');
    input.classList.remove('valid');
    if (errorEl)  errorEl.textContent  = error;
    if (statusEl) statusEl.innerHTML = window.MSEA_UI.icon('cerrar', 'icon-red');
    return false;
  } else {
    input.classList.remove('invalid');
    input.classList.add('valid');
    if (errorEl)  errorEl.textContent  = '';
    if (statusEl) statusEl.innerHTML = window.MSEA_UI.icon('check', 'icon-green');
    return true;
  }
}

/* ============================================================
   10. SIMULACIÓN DE REGISTRO
   (Reemplazar con llamada real al backend)
   ============================================================ */

function goToSuccess() {
  // Reusar la función setStep desde initSteps — accedemos vía DOM
  const step1  = document.getElementById('step-1');
  const step2  = document.getElementById('step-2');
  const stepOk = document.getElementById('step-success');
  const progress = document.getElementById('progress-fill');
  const formTitle    = document.getElementById('form-title');
  const formSubtitle = document.getElementById('form-subtitle');
  const formFooter   = document.getElementById('form-footer');

  [step1, step2, stepOk].forEach(s => s?.classList.remove('active'));
  stepOk?.classList.add('active');
  if (progress) progress.style.width = '100%';
  if (formTitle)    formTitle.textContent    = '¡Listo!';
  if (formSubtitle) formSubtitle.textContent = 'Registro completado con éxito';
  if (formFooter)   formFooter.style.display = 'none';
}

/* ============================================================
   11. FALLBACK IMAGEN DEL PANEL
   ============================================================ */
function initPanelImgFallback() {
  const img = document.querySelector('.panel-img');
  if (!img) return;
  img.addEventListener('error', () => img.classList.add('error'));
}

/* ── HELPER ── */
function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}
