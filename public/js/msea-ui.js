/* ==========================================================================
   MSEA UI: utilidades compartidas por todos los paneles.
   - esc(): escapa texto antes de insertarlo como HTML (proteccion XSS).
   - icon(): devuelve un icono SVG del sprite (#i-nombre). Sin emojis.
   - api(): fetch con CSRF, JSON o multipart y progreso de subida.
   ========================================================================== */
(function () {
  'use strict';

  const ENTIDADES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };

  /** Escapa cualquier valor para usarlo dentro de HTML o de un atributo entre comillas. */
  function esc(valor) {
    if (valor === null || valor === undefined) return '';
    return String(valor).replace(/[&<>"'`]/g, (c) => ENTIDADES[c]);
  }

  /** Solo permite URLs del mismo sitio, http(s), blob: o data:image. Lo demas se anula. */
  function safeUrl(url) {
    if (!url) return '';
    const texto = String(url).trim();
    if (/^data:image\/(png|jpe?g|webp|gif);base64,/i.test(texto)) return texto;
    if (/^blob:/i.test(texto)) return texto;
    try {
      const u = new URL(texto, window.location.origin);
      return ['http:', 'https:'].includes(u.protocol) ? u.href : '';
    } catch (e) {
      return '';
    }
  }

  const NOMBRES_ICONO = /^[a-z0-9-]+$/;

  // Sprite de iconos externo (FE-08); la URL con su version llega en <meta name="msea-iconos">.
  const SPRITE = document.querySelector('meta[name="msea-iconos"]')?.content || '';

  function icon(nombre, clase = '') {
    const n = NOMBRES_ICONO.test(nombre || '') ? nombre : 'musica';
    return `<svg class="icon ${esc(clase)}" aria-hidden="true" focusable="false"><use href="${SPRITE}#i-${n}"></use></svg>`;
  }

  /** Icono segun el nombre de un instrumento. */
  function iconoInstrumento(nombre) {
    const t = String(nombre || '').toLowerCase();
    if (t.includes('contrabajo') || t === 'bajo') return 'contrabajo';
    if (t.includes('chelo') || t.includes('cello')) return 'violonchelo';
    if (t.includes('viola')) return 'viola';
    if (t.includes('viol')) return 'violin';
    return 'musica';
  }

  function iconoTipo(tipo) {
    return ({ afinacion: 'afinacion', ritmo: 'ritmo', teoria: 'teoria', tecnica: 'tecnica', libre: 'musica',
      tarea: 'tareas', xp: 'estrella', logro: 'logro', mensaje: 'mensaje', comunicado: 'campana', practica: 'musica',
      leccion: 'cursos', saldo_inicial: 'estrella' })[tipo] || 'campana';
  }

  function vacio(titulo, texto = '', icono = 'info') {
    return `<div class="empty-state">${icon(icono)}<strong>${esc(titulo)}</strong>${texto ? `<p>${esc(texto)}</p>` : ''}</div>`;
  }

  function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function irAlLogin() {
    window.location.href = '/login';
  }

  /** Pide un token CSRF nuevo y lo copia en la meta y en todos los formularios abiertos. */
  async function refrescarToken() {
    const r = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok) throw new Error('No se pudo renovar la sesión. Recarga la página.');
    const { token } = await r.json();
    if (!token) throw new Error('No se pudo renovar la sesión. Recarga la página.');
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = token;
    document.querySelectorAll('input[name="_token"]').forEach((i) => { i.value = token; });
    return token;
  }

  /** Primer mensaje de error legible de una respuesta de Laravel. */
  function mensajeError(data, porDefecto) {
    if (data && data.errors) {
      const primero = Object.values(data.errors)[0];
      if (Array.isArray(primero) && primero[0]) return primero[0];
    }
    return (data && data.message) || porDefecto || 'Ocurrio un error. Intenta de nuevo.';
  }

  /**
   * Peticion al servidor.
   * opciones: { method, json, form (FormData), onProgress(pct) }
   * Rechaza con Error(mensaje) y error.errors cuando hay validacion.
   */
  function api(url, opciones = {}) {
    const metodo = opciones.method || (opciones.json || opciones.form ? 'POST' : 'GET');

    if (opciones.form && typeof opciones.onProgress === 'function') {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(metodo, url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) opciones.onProgress(Math.round((e.loaded / e.total) * 100));
        });
        xhr.addEventListener('load', () => {
          let data = {};
          try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) { data = {}; }
          if (xhr.status >= 200 && xhr.status < 300) return resolve(data);
          if (xhr.status === 419 && !opciones._reintento) {
            return refrescarToken().then(() => resolve(api(url, { ...opciones, _reintento: true }))).catch(reject);
          }
          if (xhr.status === 401) { irAlLogin(); }
          const err = new Error(xhr.status === 413 ? 'El archivo es demasiado grande.' : mensajeError(data));
          err.errors = data.errors || null;
          err.status = xhr.status;
          reject(err);
        });
        xhr.addEventListener('error', () => reject(new Error('Sin conexion con el servidor. Revisa tu internet.')));
        xhr.send(opciones.form);
      });
    }

    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() };
    let body;
    if (opciones.json !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opciones.json);
    } else if (opciones.form) {
      body = opciones.form;
    }

    return fetch(url, { method: metodo, headers, body, credentials: 'same-origin' })
      .catch(() => { throw new Error('Sin conexion con el servidor. Revisa tu internet.'); })
      .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (r.status === 419 && !opciones._reintento) {
          // Token CSRF vencido (pagina abierta mucho tiempo): se pide uno nuevo y se reintenta una vez.
          await refrescarToken();
          return api(url, { ...opciones, _reintento: true });
        }
        if (r.status === 401) {
          irAlLogin();
        }
        if (!r.ok) {
          const err = new Error(r.status === 413 ? 'El archivo es demasiado grande.' : mensajeError(data));
          err.errors = data.errors || null;
          err.status = r.status;
          throw err;
        }
        return data;
      });
  }

  /** Muestra estado "cargando" en un boton mientras dura la promesa. */
  async function conCarga(boton, promesa) {
    if (boton) {
      boton.disabled = true;
      boton.classList.add('is-loading');
      boton.setAttribute('aria-busy', 'true');
    }
    try {
      return await promesa;
    } finally {
      if (boton) {
        boton.disabled = false;
        boton.classList.remove('is-loading');
        boton.removeAttribute('aria-busy');
      }
    }
  }

  function toast(mensaje, duracion = 3000) {
    let el = document.getElementById('toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast';
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.setAttribute('role', 'status');
    el.textContent = mensaje;
    el.classList.add('show');
    clearTimeout(el._timer);
    el._timer = setTimeout(() => el.classList.remove('show'), duracion);
  }

  /** Aviso que debe verse despues de recargar la pagina (p. ej. «Tarea creada»). */
  function toastTrasRecargar(mensaje) {
    try { sessionStorage.setItem('msea-toast', String(mensaje)); } catch (e) { /* modo privado */ }
  }
  document.addEventListener('DOMContentLoaded', () => {
    let pendiente = null;
    try { pendiente = sessionStorage.getItem('msea-toast'); sessionStorage.removeItem('msea-toast'); } catch (e) { pendiente = null; }
    if (pendiente) toast(pendiente, 4000);
  });

  /** Marca errores de validacion junto a cada campo (name="...") y los asocia
   *  con aria-invalid y aria-describedby; el foco va al primero (A11Y-02). */
  function mostrarErrores(form, errors) {
    if (!form) return;
    const C = window.MSEA_CAMPOS;
    form.querySelectorAll('[aria-invalid="true"], .is-invalid').forEach((n) => {
      n.classList.remove('is-invalid');
      if (C) C.marcar(n, ''); else n.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('.field-error[data-creado]').forEach((n) => { n.textContent = ''; });
    Object.entries(errors || {}).forEach(([campo, mensajes]) => {
      const input = form.querySelector(`[name="${CSS.escape(campo)}"]`);
      if (!input) return;
      const texto = Array.isArray(mensajes) ? mensajes[0] : String(mensajes);
      if (C) { C.marcar(input, texto); return; }
      input.setAttribute('aria-invalid', 'true');
      const p = document.createElement('p');
      p.className = 'field-error';
      p.textContent = texto;
      input.insertAdjacentElement('afterend', p);
    });
    if (C) C.enfocarPrimero(form);
  }

  function fecha(valor) {
    if (!valor) return '';
    const d = new Date(String(valor).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? String(valor) : d.toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' });
  }

  /* ---------------- Detalles de experiencia ---------------- */

  const reduceMovimiento = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /** Confeti ligero (sin librerias). Se omite si la persona prefiere menos movimiento. */
  function celebrar(opciones = {}) {
    if (reduceMovimiento()) return;
    const colores = opciones.colores || ['#8CC63F', '#A8D858', '#E6B84A', '#4D7EA8', '#315C3A', '#D95C5C'];
    const canvas = document.createElement('canvas');
    canvas.className = 'msea-confeti';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const W = window.innerWidth, H = window.innerHeight;
    canvas.width = W * dpr; canvas.height = H * dpr; ctx.scale(dpr, dpr);
    const origenX = opciones.x ?? W / 2, origenY = opciones.y ?? H / 3;
    const piezas = Array.from({ length: opciones.cantidad || 90 }, () => ({
      x: origenX, y: origenY,
      vx: (Math.random() - 0.5) * 12, vy: Math.random() * -11 - 3,
      w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
      rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3,
      color: colores[Math.floor(Math.random() * colores.length)],
    }));
    const inicio = performance.now();
    (function cuadro(t) {
      const dt = t - inicio;
      ctx.clearRect(0, 0, W, H);
      piezas.forEach((p) => {
        p.vy += 0.28; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
        ctx.globalAlpha = Math.max(0, 1 - dt / 2600);
        ctx.fillStyle = p.color; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      if (dt < 2600) requestAnimationFrame(cuadro); else canvas.remove();
    })(inicio);
  }

  /** "+15 XP" que sube y se desvanece desde un elemento (o el centro de la pantalla). */
  function xpFlotante(cantidad, desde) {
    if (!cantidad) return;
    const el = document.createElement('div');
    el.className = 'msea-xp-flotante';
    el.textContent = `+${cantidad} XP`;
    const r = desde?.getBoundingClientRect?.();
    el.style.left = `${r ? r.left + r.width / 2 : window.innerWidth / 2}px`;
    el.style.top = `${r ? r.top : window.innerHeight / 2}px`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1600);
  }

  /** Cuenta animada hasta el valor final (data-contar="123"). */
  function animarNumeros(raiz = document) {
    raiz.querySelectorAll('[data-contar]').forEach((el) => {
      const fin = Number(el.dataset.contar);
      if (!Number.isFinite(fin)) return;
      const prefijo = el.dataset.prefijo || '';
      if (reduceMovimiento() || fin === 0) { el.textContent = prefijo + fin.toLocaleString('es-BO'); return; }
      const dur = 700, t0 = performance.now();
      (function paso(t) {
        const k = Math.min(1, (t - t0) / dur);
        const v = Math.round(fin * (1 - Math.pow(1 - k, 3)));
        el.textContent = prefijo + v.toLocaleString('es-BO');
        if (k < 1) requestAnimationFrame(paso);
      })(t0);
      el.removeAttribute('data-contar');
    });
  }

  const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  /** Mantiene el Tab dentro del dialogo abierto (A11Y-05). */
  function atraparFoco(caja, e) {
    if (e.key !== 'Tab') return;
    const nodos = [...caja.querySelectorAll(ENFOCABLES)].filter((n) => n.offsetParent !== null || n === document.activeElement);
    if (!nodos.length) { e.preventDefault(); return; }
    const primero = nodos[0];
    const ultimo = nodos[nodos.length - 1];
    if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
  }

  /**
   * Dialogo ya escrito en la pagina (.dialogo-fondo con [role="dialog"] dentro):
   * al abrir lleva el foco adentro, lo mantiene ahi, cierra con Escape o
   * tocando el fondo, y al cerrar devuelve el foco al boton que lo abrio.
   */
  function dialogo(fondo, { alCerrar } = {}) {
    let anterior = null;
    const caja = () => fondo?.querySelector('[role="dialog"]') || fondo;
    const tecla = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); cerrar(); return; }
      atraparFoco(caja(), e);
    };
    function abrir() {
      if (!fondo) return;
      anterior = document.activeElement;
      fondo.classList.remove('hidden');
      document.addEventListener('keydown', tecla);
      const destino = caja().querySelector('[autofocus]') || caja().querySelector(ENFOCABLES);
      (destino || caja()).focus?.();
    }
    function cerrar() {
      if (!fondo || fondo.classList.contains('hidden')) return;
      fondo.classList.add('hidden');
      document.removeEventListener('keydown', tecla);
      if (typeof alCerrar === 'function') alCerrar();
      if (anterior && document.contains(anterior)) anterior.focus?.();
    }
    fondo?.addEventListener('click', (e) => { if (e.target === fondo) cerrar(); });
    return { abrir, cerrar };
  }

  /**
   * Cambio de seccion en un panel de una sola pagina (A11Y-07): actualiza el
   * titulo de la pestana y lleva el foco al encabezado de la vista, asi el
   * lector de pantalla anuncia «Mis tareas, encabezado nivel 2».
   */
  function anunciarVista(destino, etiqueta, { base = 'MSEA', enfocar = true } = {}) {
    if (!destino) return;
    const limpio = String(etiqueta || '').replace(/\s+\d+$/, '').trim();
    if (!anunciarVista.base) anunciarVista.base = document.title;
    document.title = limpio ? `${limpio} · ${base}` : anunciarVista.base;
    if (!enfocar) return;
    const titulo = destino.querySelector('h1, h2') || document.querySelector('.app-main h1, .app-main h2');
    if (!titulo) return;
    if (!titulo.hasAttribute('tabindex')) titulo.setAttribute('tabindex', '-1');
    titulo.focus({ preventScroll: true });
  }

  /** Dialogo modal simple y accesible (cierra con Escape o el boton). */
  function modal({ titulo, html, icono = 'logro', boton = 'Continuar', clase = '' }) {
    const fondo = document.createElement('div');
    fondo.className = `msea-modal-fondo ${clase}`;
    fondo.innerHTML = `<div class="msea-modal" role="dialog" aria-modal="true" aria-labelledby="msea-modal-titulo">
        <div class="msea-modal-icono">${icon(icono)}</div>
        <h2 id="msea-modal-titulo">${esc(titulo)}</h2>
        <div class="msea-modal-cuerpo">${html || ''}</div>
        <button type="button" class="btn-msea msea-modal-ok">${esc(boton)}</button>
      </div>`;
    const anterior = document.activeElement;
    const cerrar = () => { fondo.remove(); document.removeEventListener('keydown', tecla); anterior?.focus?.(); };
    const tecla = (e) => { if (e.key === 'Escape') cerrar(); else atraparFoco(fondo, e); };
    fondo.addEventListener('click', (e) => { if (e.target === fondo) cerrar(); });
    fondo.querySelector('.msea-modal-ok').addEventListener('click', cerrar);
    document.addEventListener('keydown', tecla);
    document.body.appendChild(fondo);
    fondo.querySelector('.msea-modal-ok').focus();
    return cerrar;
  }

  function saludo(nombre) {
    const h = new Date().getHours();
    const s = h >= 5 && h < 12 ? 'Buenos días' : h >= 12 && h < 19 ? 'Buenas tardes' : 'Buenas noches';
    return nombre ? `${s}, ${nombre}` : s;
  }

  /* ---------------- Sesion: flecha atras y paneles abiertos mucho tiempo ---------------- */

  // Si el navegador restaura la pagina desde su cache (flecha atras), se recarga para
  // que el servidor decida: panel actualizado o pantalla de inicio de sesion.
  window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });

  // En los paneles con sesion se renueva el token cada 15 minutos mientras la pestana
  // esta visible: asi un formulario abierto durante una practica larga no da error 419.
  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('form[action$="/logout"]')) return;
    setInterval(() => {
      if (document.visibilityState === 'visible') refrescarToken().catch(() => {});
    }, 15 * 60 * 1000);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && Date.now() - (window.__mseaUltimoToken || 0) > 10 * 60 * 1000) {
        window.__mseaUltimoToken = Date.now();
        refrescarToken().catch(() => {});
      }
    });
    window.__mseaUltimoToken = Date.now();
  });

  /* ---------------- Menu del usuario (avatar de la cabecera) ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-menu-usuario]').forEach((menu) => {
      const boton = menu.querySelector('[data-menu-usuario-boton]');
      const cerrar = () => { menu.classList.remove('abierto'); boton?.setAttribute('aria-expanded', 'false'); };
      boton?.addEventListener('click', (e) => {
        e.stopPropagation();
        const abierto = menu.classList.toggle('abierto');
        boton.setAttribute('aria-expanded', String(abierto));
        if (abierto) menu.querySelector('.menu-usuario-panel a, .menu-usuario-panel button')?.focus();
      });
      menu.querySelectorAll('.menu-usuario-panel a').forEach((a) => a.addEventListener('click', cerrar));
      document.addEventListener('click', (e) => { if (!menu.contains(e.target)) cerrar(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu.classList.contains('abierto')) { cerrar(); boton?.focus(); } });
    });
  });

  window.MSEA_UI = { esc, safeUrl, icon, iconoInstrumento, iconoTipo, vacio, api, conCarga, toast, toastTrasRecargar, mostrarErrores, fecha, csrf,
    refrescarToken, celebrar, xpFlotante, animarNumeros, modal, dialogo, atraparFoco, anunciarVista, saludo };
})();
