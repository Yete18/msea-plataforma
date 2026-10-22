// Cajón de navegación de los paneles en celular y tableta (UX-05).
// Botón con aria-expanded, cierre con Esc, con el velo o al elegir un enlace,
// y el foco vuelve al botón al cerrar.
(() => {
  // Barras de progreso: el ancho o alto llega en data-ancho / data-alto (0-100) y se
  // aplica por CSSOM. Así no hay atributos style="" y la CSP no necesita
  // 'unsafe-inline' en los estilos (FE-09). Vale también para lo que se pinta después.
  const medir = (raiz) => {
    if (!raiz || raiz.nodeType !== 1) return;
    const nodos = raiz.matches?.('[data-ancho], [data-alto]') ? [raiz] : [];
    nodos.push(...raiz.querySelectorAll('[data-ancho], [data-alto]'));
    nodos.forEach((n) => {
      const pct = (v) => `${Math.max(0, Math.min(100, Number(v) || 0))}%`;
      if (n.dataset.ancho !== undefined) n.style.width = pct(n.dataset.ancho);
      if (n.dataset.alto !== undefined) n.style.height = pct(n.dataset.alto);
    });
  };
  medir(document.body);
  new MutationObserver((cambios) => cambios.forEach((c) => c.addedNodes.forEach(medir)))
    .observe(document.body, { childList: true, subtree: true });

  // Alertas que se pueden cerrar (también en las páginas de acceso).
  document.querySelectorAll('[data-cerrar-alerta]').forEach((b) => b.addEventListener('click', () => b.closest('.alerta')?.remove()));

  /* Errores de validación asociados a su campo (A11Y-02): el lector de pantalla
     anuncia «Correo, inválido, Ingresa tu correo electrónico». */
  const idDe = (input) => input.id || (input.id = `campo-${String(input.name || 'x').replace(/[^\w-]/g, '-')}-${Math.random().toString(36).slice(2, 7)}`);
  const cajaError = (input) => {
    const propios = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean)
      .map((id) => document.getElementById(id)).filter((n) => n && n.classList.contains('field-error'));
    if (propios[0]) return propios[0];
    const p = document.createElement('p');
    p.className = 'field-error';
    p.id = `${idDe(input)}-error`;
    p.dataset.creado = '';
    (input.closest('.input-wrapper') || input).insertAdjacentElement('afterend', p);
    input.setAttribute('aria-describedby', `${input.getAttribute('aria-describedby') || ''} ${p.id}`.trim());
    return p;
  };
  const CAMPOS = {
    /** Marca (o limpia, con mensaje vacío) el error de un campo o grupo. */
    marcar(input, mensaje) {
      if (!input) return;
      if (mensaje) {
        cajaError(input).textContent = mensaje;
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
        const caja = (input.getAttribute('aria-describedby') || '').split(/\s+/).map((id) => document.getElementById(id))
          .find((n) => n && n.classList.contains('field-error'));
        if (caja) caja.textContent = '';
      }
    },
    /** Lleva el foco al primer campo inválido del formulario. */
    enfocarPrimero(raiz) {
      const primero = raiz?.querySelector('[aria-invalid="true"]');
      if (!primero) return;
      const destino = primero.matches('input, select, textarea, button, [tabindex]') ? primero : primero.querySelector('button, input, select, textarea');
      destino?.focus();
    },
  };
  window.MSEA_CAMPOS = CAMPOS;

  // Errores que devolvió el servidor al recargar la página: se marcan en el
  // formulario que tiene esos campos (si hay varios iguales, solo queda la alerta).
  const datos = document.getElementById('msea-errores-campos');
  if (datos) {
    let errores = {};
    try { errores = JSON.parse(datos.textContent || '{}'); } catch (e) { errores = {}; }
    const nombres = Object.keys(errores).filter((n) => !n.startsWith('_'));
    const candidatos = [...document.querySelectorAll('form')].map((f) => ({
      f, n: nombres.filter((n) => f.querySelector(`[name="${CSS.escape(n)}"]`)).length,
    })).filter((c) => c.n > 0).sort((a, b) => b.n - a.n);
    if (candidatos.length && (candidatos.length === 1 || candidatos[0].n > candidatos[1].n)) {
      const form = candidatos[0].f;
      nombres.forEach((n) => {
        const campo = form.querySelector(`[name="${CSS.escape(n)}"]`);
        if (!campo || campo.type === 'hidden') return;
        CAMPOS.marcar(campo, [].concat(errores[n])[0]);
        campo.addEventListener('input', () => CAMPOS.marcar(campo, ''), { once: true });
      });
      CAMPOS.enfocarPrimero(form);
    }
  }
  // Un error general que llegó al recargar (p. ej. «contraseña incorrecta») recibe
  // el foco para que el lector lo lea, si ningún campo lo tomó antes.
  const errorGeneral = document.querySelector('.alerta--error');
  if (errorGeneral && (document.activeElement === document.body || !document.activeElement)) {
    errorGeneral.setAttribute('tabindex', '-1');
    errorGeneral.focus({ preventScroll: false });
  }

  const app = document.querySelector('[data-panel-app]');
  if (!app) return;
  const boton = app.querySelector('[data-abrir-menu]');
  const lateral = app.querySelector('#menu-panel');
  const velo = app.querySelector('[data-cerrar-menu]');
  const movil = window.matchMedia('(max-width: 1023px)');

  const abrir = () => {
    app.setAttribute('data-menu-abierto', '');
    boton?.setAttribute('aria-expanded', 'true');
    lateral?.removeAttribute('inert');
    (lateral?.querySelector('[aria-current="page"]') || lateral?.querySelector('a, button'))?.focus();
  };
  const cerrar = (devolverFoco = true) => {
    app.removeAttribute('data-menu-abierto');
    boton?.setAttribute('aria-expanded', 'false');
    if (movil.matches) lateral?.setAttribute('inert', '');
    if (devolverFoco) boton?.focus();
  };
  const ajustar = () => {
    if (movil.matches) {
      if (!app.hasAttribute('data-menu-abierto')) lateral?.setAttribute('inert', '');
    } else {
      app.removeAttribute('data-menu-abierto');
      boton?.setAttribute('aria-expanded', 'false');
      lateral?.removeAttribute('inert');
    }
  };

  boton?.addEventListener('click', () => (app.hasAttribute('data-menu-abierto') ? cerrar() : abrir()));
  velo?.addEventListener('click', () => cerrar());
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && app.hasAttribute('data-menu-abierto')) cerrar();
  });
  lateral?.querySelectorAll('a[href^="#"]').forEach((a) => a.addEventListener('click', () => movil.matches && cerrar(false)));
  movil.addEventListener('change', ajustar);
  ajustar();

  // Tablas con desplazamiento horizontal: se pueden enfocar y recorrer con el
  // teclado, y anuncian que contienen (A11Y, axe scrollable-region-focusable).
  const marcarTablas = () => document.querySelectorAll('.table-wrap').forEach((caja) => {
    const desborda = caja.scrollWidth > caja.clientWidth + 1;
    if (desborda) {
      caja.setAttribute('tabindex', '0');
      caja.setAttribute('role', 'region');
      if (!caja.hasAttribute('aria-label')) {
        const titulo = caja.closest('section')?.querySelector('h2, h3');
        caja.setAttribute('aria-label', (titulo ? titulo.textContent.trim() + ': ' : '') + 'tabla, desliza para ver más columnas');
      }
    } else if (caja.getAttribute('tabindex') === '0') {
      caja.removeAttribute('tabindex');
      caja.removeAttribute('role');
    }
  });
  marcarTablas();
  window.addEventListener('resize', marcarTablas);

})();
