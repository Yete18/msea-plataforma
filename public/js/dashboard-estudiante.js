/* ============================================================
   MSEA - Panel del estudiante
   Reglas de esta version (auditoria 2026-10):
   - Todo dato del servidor se inserta escapado (MSEA_UI.esc) o con textContent.
   - No hay datos de demostracion: si no hay datos se muestra un estado vacio.
   - El XP lo calcula el servidor; el navegador nunca envia cantidades de XP.
   - Las metricas del afinador y del ritmo se marcan como NO verificadas.
   ============================================================ */
(function () {
  'use strict';

  const UI = window.MSEA_UI;
  const { esc, icon, vacio, api, conCarga, toast } = UI;

  const ESTUDIANTE = Object.assign({
    nombre: '', nombreCompleto: '', nivel: 1, xp: 0, xpMax: 500, puntos: 0, racha: 0, rankingPos: 0,
    ejerciciosHechos: 0, logrosDesbloqueados: 0, practicaTopeDiario: 0, practicaXpHoy: 0,
  }, window.MSEA_ESTUDIANTE || {});

  const lista = (v) => (Array.isArray(v) ? v : []);
  const TAREAS = lista(window.MSEA_TAREAS);
  const RANKING = lista(window.MSEA_RANKING);
  const LOGROS = lista(window.MSEA_LOGROS);
  const EJERCICIOS = lista(window.MSEA_EJERCICIOS);
  const NOTIFICACIONES = lista(window.MSEA_NOTIFICACIONES);
  const ACTIVIDAD = lista(window.MSEA_ACTIVIDAD);
  const CURSOS = lista(window.MSEA_CURSOS);
  const TEORIA = lista(window.MSEA_TEORIA);
  const MENSAJES = lista(window.MSEA_MENSAJES);
  const CONTACTOS = lista(window.MSEA_CONTACTOS);

  const ESTADOS = { pendiente: 'Pendiente', entregada: 'Entregada', devuelta: 'Para corregir', calificada: 'Calificada' };
  let filtroTareas = 'todas';

  document.addEventListener('DOMContentLoaded', () => {
    renderResumen();
    initNavegacion();
    initNotificaciones();
    renderTareasInicio();
    renderTareasCompletas();
    renderCursos();
    renderRanking();
    renderLogros();
    renderProgreso();
    renderEjercicios();
    renderTeoria();
    renderMensajes();
    initPerfil();
    initEntrega();
    initAfinador();
    initMetronomo();
    initPracticaLibre();
    initMensajes();
    initInicio();

    const inicial = (window.location.hash || '').replace('#', '');
    if (inicial) navegar(inicial, { enfocar: false });
    window.addEventListener('hashchange', () => navegar(window.location.hash.replace('#', '')));
  });

  /* ---------------------------------------------------------- utilidades */

  function setText(id, valor) {
    const el = document.getElementById(id);
    if (el) el.textContent = valor === null || valor === undefined || valor === '' ? 'Sin registrar' : String(valor);
  }

  function numero(n) {
    return Number(n || 0).toLocaleString('es-BO');
  }

  function pendientes() {
    return TAREAS.filter((t) => t.estado === 'pendiente' || t.estado === 'devuelta');
  }

  /* ---------------------------------------------------------- resumen / cabecera */

  function renderResumen() {
    const hora = new Date().getHours();
    const saludo = hora >= 6 && hora < 12 ? 'Buenos días' : hora >= 12 && hora < 19 ? 'Buenas tardes' : 'Buenas noches';
    const titulo = document.getElementById('greeting-title');
    if (titulo) titulo.textContent = `${saludo}, ${ESTUDIANTE.nombre || 'estudiante'}`;
    actualizarSubtitulo('inicio');

    const pct = ESTUDIANTE.xpMax ? Math.min(100, (ESTUDIANTE.xp / ESTUDIANTE.xpMax) * 100) : 0;
    ['s-xp-fill', 'perfil-xp-fill'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.width = `${pct}%`;
    });
    document.querySelector('.s-xp-track')?.setAttribute('aria-valuenow', String(Math.round(pct)));
    setText('s-xp-text', `${ESTUDIANTE.xp} / ${ESTUDIANTE.xpMax}`);
    setText('perfil-xp-text', `${ESTUDIANTE.xp} / ${ESTUDIANTE.xpMax} XP`);
    setText('perfil-nivel-actual', `Nivel ${ESTUDIANTE.nivel}`);
    setText('perfil-nivel-siguiente', `Nivel ${Number(ESTUDIANTE.nivel) + 1}`);
    setText('perfil-xp-hint', `Te faltan ${Math.max(0, ESTUDIANTE.xpMax - ESTUDIANTE.xp)} XP para subir de nivel.`);

    setText('s-name', ESTUDIANTE.nombreCompleto || ESTUDIANTE.nombre);
    setText('s-role', `${ESTUDIANTE.instrumento || 'Sin instrumento'} - Nivel ${ESTUDIANTE.nivel}`);
    setText('s-streak-num', ESTUDIANTE.racha);

    setText('perfil-nombre-big', ESTUDIANTE.nombreCompleto);
    setText('perfil-rol-big', `${ESTUDIANTE.instrumento || 'Sin instrumento'} - Nivel ${ESTUDIANTE.nivel}`);
    setText('perfil-racha-val', ESTUDIANTE.racha);
    setText('perfil-pts-val', numero(ESTUDIANTE.puntos));
    setText('perfil-rank-val', ESTUDIANTE.rankingPos ? `#${ESTUDIANTE.rankingPos}` : '-');

    const tope = Number(ESTUDIANTE.practicaTopeDiario || 0);
    const hoy = Number(ESTUDIANTE.practicaXpHoy || 0);
    setText('tope-diario', Math.max(0, tope - hoy));

    ['badge-tareas', 'burbuja-tareas'].forEach((id) => {
      const badge = document.getElementById(id);
      if (!badge) return;
      badge.textContent = String(pendientes().length);
      badge.classList.toggle('hidden', pendientes().length === 0);
    });

    renderTarjetaNivel();
    renderMeta();
    renderSiguientePaso();
    renderAvisoRacha();
    aplicarAvatar(ESTUDIANTE.foto);
  }

  /* ---------------------------------------------------------- inicio: nivel, meta diaria y siguiente paso */

  function renderTarjetaNivel() {
    const pct = ESTUDIANTE.xpMax ? Math.min(100, (ESTUDIANTE.xp / ESTUDIANTE.xpMax) * 100) : 0;
    setText('tn-nivel', `Nv. ${ESTUDIANTE.nivel}`);
    setText('tn-titulo', ESTUDIANTE.tituloNivel || '');
    setText('tn-racha-num', ESTUDIANTE.racha);
    document.getElementById('tn-racha')?.classList.toggle('apagada', !Number(ESTUDIANTE.racha));
    const relleno = document.getElementById('tn-relleno');
    if (relleno) requestAnimationFrame(() => { relleno.style.width = `${pct}%`; });
    document.getElementById('tn-barra')?.setAttribute('aria-valuenow', String(Math.round(pct)));
    setText('tn-xp', `${numero(ESTUDIANTE.xp)} / ${numero(ESTUDIANTE.xpMax)} XP`);
    setText('tn-faltan', `Nivel ${Number(ESTUDIANTE.nivel) + 1} en ${numero(Math.max(0, ESTUDIANTE.xpMax - ESTUDIANTE.xp))} XP`);
    const total = document.getElementById('tn-total');
    if (total) { total.dataset.contar = String(Number(ESTUDIANTE.puntos || 0)); }
    const practicas = document.getElementById('tn-practicas');
    if (practicas) { practicas.dataset.contar = String(Number(ESTUDIANTE.ejerciciosHechos || 0)); }
    setText('tn-ranking', ESTUDIANTE.rankingPos ? `#${ESTUDIANTE.rankingPos}` : '-');
    UI.animarNumeros(document.querySelector('.tarjeta-nivel') || document);
  }

  function metaActual() {
    return Number(ESTUDIANTE.metaDiaria || 15);
  }

  function renderMeta() {
    const meta = metaActual();
    const minutos = Math.floor(Number(ESTUDIANTE.minutosHoy || 0));
    const pct = Math.min(100, (minutos / meta) * 100);
    const anillo = document.getElementById('tm-anillo');
    if (anillo) requestAnimationFrame(() => anillo.setAttribute('stroke-dasharray', `${pct} 100`));
    document.querySelector('.tarjeta-meta')?.classList.toggle('cumplida', minutos >= meta);
    setText('tm-minutos', minutos);
    setText('tm-de', `de ${meta} min`);
    const faltan = Math.max(0, meta - minutos);
    setText('tm-estado', minutos >= meta
      ? '¡Meta cumplida! Cada minuto extra también suma.'
      : minutos > 0 ? `¡Vas bien! Te faltan ${faltan} min.` : `Practica ${meta} minutos para cumplir tu meta.`);
    const semana = Array.isArray(ESTUDIANTE.semana) ? ESTUDIANTE.semana : [];
    const activos = semana.filter((d) => d.minutos > 0).length;
    setText('tm-sub', activos ? `Esta semana practicaste ${activos} día(s).` : 'Aún no practicaste esta semana.');
    const cont = document.getElementById('tm-semana');
    if (cont) {
      cont.innerHTML = semana.map((d) => {
        const clase = d.cumplida ? 'cumplido' : d.minutos > 0 ? 'parcial' : d.futuro ? 'futuro' : 'vacio';
        const titulo = d.futuro ? 'Próximamente' : `${Math.round(d.minutos)} min`;
        return `<span class="tm-dia ${clase} ${d.hoy ? 'hoy' : ''}" title="${esc(titulo)}"><i>${d.cumplida ? icon('check') : ''}</i><b>${esc(d.dia)}</b></span>`;
      }).join('');
    }
  }

  /** Elige la accion mas util ahora: corregir, entregar lo que vence antes, seguir un curso o practicar. */
  function siguientePaso() {
    const devuelta = TAREAS.find((t) => t.estado === 'devuelta');
    if (devuelta) {
      return { etiqueta: 'Tu profesor te pidió corregir', titulo: devuelta.nombre, detalle: devuelta.comentarioProfesor ? `“${devuelta.comentarioProfesor}”` : 'Revisa la observación y vuelve a entregar.', boton: 'Corregir tarea', pagina: 'tareas' };
    }
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    const porVencer = pendientes().filter((t) => t.estado === 'pendiente')
      .sort((a, b) => String(a.venceIso || '9999').localeCompare(String(b.venceIso || '9999')))[0];
    if (porVencer) {
      let cuando = 'sin fecha límite';
      if (porVencer.venceIso) {
        const dias = Math.round((new Date(`${porVencer.venceIso}T00:00:00`) - hoy) / 86400000);
        cuando = dias < 0 ? `venció hace ${-dias} día(s)` : dias === 0 ? 'vence hoy' : dias === 1 ? 'vence mañana' : `vence en ${dias} días`;
      }
      return { etiqueta: 'Tarea pendiente', titulo: porVencer.nombre, detalle: `${porVencer.profesor} · ${cuando} · +${Number(porVencer.xp)} XP`, boton: 'Ver y entregar', pagina: 'tareas' };
    }
    const curso = CURSOS.find((c) => Number(c.progreso) < 100);
    if (curso) {
      return { etiqueta: 'Continúa tu curso', titulo: curso.titulo, detalle: `${Number(curso.completadas)} de ${Number(curso.lecciones)} lecciones completadas`, boton: curso.completadas ? 'Continuar lección' : 'Empezar curso', url: curso.url };
    }
    const faltan = Math.max(0, metaActual() - Math.floor(Number(ESTUDIANTE.minutosHoy || 0)));
    return faltan > 0
      ? { etiqueta: 'Práctica de hoy', titulo: `Practica ${faltan} minutos más`, detalle: 'Cumple tu meta diaria y mantén tu racha.', boton: 'Empezar a practicar', pagina: 'ejercicios' }
      : { etiqueta: 'Práctica de hoy', titulo: '¡Cumpliste tu meta de hoy!', detalle: 'Si quieres, responde preguntas de teoría para sumar más XP.', boton: 'Ir a teoría', pagina: 'ejercicios' };
  }

  function renderSiguientePaso() {
    const paso = siguientePaso();
    setText('sp-etiqueta', paso.etiqueta);
    setText('sp-titulo', paso.titulo);
    const detalle = document.getElementById('sp-detalle');
    if (detalle) detalle.textContent = paso.detalle || '';
    setText('sp-boton-texto', paso.boton);
    const boton = document.getElementById('sp-boton');
    if (boton) {
      if (paso.url) {
        boton.href = UI.safeUrl(paso.url) || '#';
        boton.classList.remove('nav-link-btn');
        delete boton.dataset.page;
      } else {
        boton.href = `#${paso.pagina}`;
        boton.dataset.page = paso.pagina;
        boton.classList.add('nav-link-btn');
      }
    }
  }

  function renderAvisoRacha() {
    const aviso = document.getElementById('aviso-racha');
    if (!aviso) return;
    // Recordatorio amable (UX-06): sin cuenta regresiva ni "en riesgo". Solo
    // aparece por la tarde si aun no hubo actividad hoy. La racha admite un
    // dia de descanso, asi que no se pierde por un dia sin practicar.
    const racha = Number(ESTUDIANTE.racha || 0);
    const hora = new Date().getHours();
    const mostrar = racha > 0 && !ESTUDIANTE.practicoHoy && hora >= 17;
    aviso.classList.toggle('hidden', !mostrar);
    if (mostrar) {
      setText('aviso-racha-titulo', '¿Unos minutos de música hoy?');
      setText('aviso-racha-texto', racha === 1
        ? 'Ayer empezaste muy bien. Con poco tiempo ya cuenta.'
        : `Llevas ${racha} días practicando. Con poco tiempo ya cuenta, y si hoy descansas no pasa nada.`);
    }
  }

  const NOMBRES_META = { 10: 'Suave', 15: 'Constante', 20: 'Comprometido', 30: 'Intenso', 45: 'Profesional' };

  function elegirMeta(bienvenida = false) {
    const metas = Array.isArray(ESTUDIANTE.metasPosibles) && ESTUDIANTE.metasPosibles.length ? ESTUDIANTE.metasPosibles : [10, 15, 20, 30, 45];
    const actual = ESTUDIANTE.metaDiaria || 15;
    const cerrar = UI.modal({
      clase: 'modal-meta',
      titulo: bienvenida ? `¡Hola, ${ESTUDIANTE.nombre || 'músico'}!` : 'Tu meta diaria',
      icono: 'reloj',
      boton: 'Ahora no',
      html: `<p>${bienvenida ? 'Los músicos mejoran practicando un poco cada día. ' : ''}¿Cuántos minutos quieres practicar al día?</p>
        <div class="msea-modal-opciones">${metas.map((m) => `<button type="button" data-meta="${Number(m)}" class="${Number(m) === Number(actual) ? 'elegido' : ''}">${Number(m)} min<small>${esc(NOMBRES_META[m] || '')}</small></button>`).join('')}</div>`,
    });
    document.querySelectorAll('.msea-modal-opciones [data-meta]').forEach((b) => {
      b.addEventListener('click', async () => {
        try {
          const r = await conCarga(b, api('/dashboard-estudiante/meta-diaria', { json: { minutos: Number(b.dataset.meta) } }));
          ESTUDIANTE.metaDiaria = r.meta;
          if (Array.isArray(r.semana)) ESTUDIANTE.semana = r.semana;
          cerrar();
          renderMeta();
          renderSiguientePaso();
          toast(r.message);
          if (bienvenida) UI.celebrar();
        } catch (err) {
          toast(err.message);
        }
      });
    });
  }

  /** Lleva a la herramienta de practica elegida desde los accesos rapidos. */
  function irAPracticar(tipo) {
    navegar('ejercicios');
    const ids = { afinacion: 'titulo-afinador', ritmo: 'titulo-ritmo', teoria: 'titulo-teoria', tecnica: 'titulo-sesion' };
    if (tipo === 'tecnica') {
      const sel = document.getElementById('practica-tipo');
      if (sel && [...sel.options].some((o) => o.value === 'tecnica')) sel.value = 'tecnica';
    }
    setTimeout(() => {
      const tarjeta = document.getElementById(ids[tipo])?.closest('.card');
      if (!tarjeta) return;
      tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' });
      tarjeta.classList.add('resaltada');
      setTimeout(() => tarjeta.classList.remove('resaltada'), 1600);
    }, 250);
  }

  /** Celebra lo que paso mientras no estaba (subio de nivel o logro nuevo calificado por el profesor). */
  function novedadesDesdeUltimaVisita() {
    const clave = `msea-visto-${Number(ESTUDIANTE.idEstudiante || 0)}`;
    let visto = null;
    try { visto = JSON.parse(localStorage.getItem(clave) || 'null'); } catch (e) { visto = null; }
    const ahora = { nivel: Number(ESTUDIANTE.nivel), logros: Number(ESTUDIANTE.logrosDesbloqueados || 0) };
    try { localStorage.setItem(clave, JSON.stringify(ahora)); } catch (e) { /* sin almacenamiento: no pasa nada */ }
    if (!visto) return false;
    if (ahora.nivel > Number(visto.nivel)) {
      celebrarNivel(ahora.nivel, ESTUDIANTE.tituloNivel);
      return true;
    }
    if (ahora.logros > Number(visto.logros)) {
      const iso = (f) => String(f || '').split('/').reverse().join('-');
      const ultimo = LOGROS.filter((l) => !l.bloqueado).sort((a, b) => iso(b.fecha).localeCompare(iso(a.fecha)))[0];
      celebrarLogros([ultimo?.nombre || 'Nuevo logro']);
      return true;
    }
    return false;
  }

  function celebrarNivel(nivel, titulo) {
    UI.celebrar();
    UI.modal({ titulo: `¡Subiste al nivel ${Number(nivel)}!`, icono: 'estrella', boton: '¡Genial!', html: `<p>Ahora eres <strong>${esc(titulo || '')}</strong>.</p><p>Sigue practicando para desbloquear el siguiente título.</p>` });
  }

  function celebrarLogros(nombres) {
    if (!nombres || !nombres.length) return;
    UI.celebrar({ colores: ['#E6B84A', '#F3D27A', '#8CC63F', '#FFFFFF'] });
    UI.modal({ titulo: nombres.length > 1 ? '¡Nuevos logros!' : '¡Logro desbloqueado!', icono: 'logro', boton: 'Ver mis logros', html: nombres.map((n) => `<p><strong>${esc(n)}</strong></p>`).join('') });
    document.querySelector('.msea-modal-ok')?.addEventListener('click', () => navegar('logros'), { once: true });
  }

  /** Aplica la respuesta del servidor despues de ganar XP: nivel, logros y meta diaria. */
  function aplicarNovedades(r, origen) {
    if (!r) return;
    if (r.xp) UI.xpFlotante(r.xp, origen);
    const metaAntes = Number(ESTUDIANTE.minutosHoy || 0) >= metaActual();
    if (r.minutos_hoy !== undefined) {
      ESTUDIANTE.minutosHoy = Number(r.minutos_hoy);
      const hoy = (ESTUDIANTE.semana || []).find((d) => d.hoy);
      if (hoy) { hoy.minutos = Number(r.minutos_hoy); hoy.cumplida = hoy.minutos >= metaActual(); }
      ESTUDIANTE.practicoHoy = true;
    }
    if (r.racha !== undefined) ESTUDIANTE.racha = Number(r.racha);
    if (r.subio_nivel) { ESTUDIANTE.tituloNivel = r.titulo_nivel; }
    renderResumen();
    if (r.subio_nivel) celebrarNivel(r.nivel, r.titulo_nivel);
    else if (r.logros_nuevos && r.logros_nuevos.length) celebrarLogros(r.logros_nuevos);
    else if (!metaAntes && Number(ESTUDIANTE.minutosHoy || 0) >= metaActual()) {
      UI.celebrar();
      toast('¡Cumpliste tu meta diaria de práctica!', 4000);
    }
    try {
      localStorage.setItem(`msea-visto-${Number(ESTUDIANTE.idEstudiante || 0)}`, JSON.stringify({ nivel: Number(ESTUDIANTE.nivel), logros: Number(ESTUDIANTE.logrosDesbloqueados || 0) + (r.logros_nuevos || []).length }));
    } catch (e) { /* opcional */ }
  }

  function initInicio() {
    document.getElementById('tm-cambiar')?.addEventListener('click', () => elegirMeta(false));
    document.querySelectorAll('[data-practicar]').forEach((b) => b.addEventListener('click', () => irAPracticar(b.dataset.practicar)));
    // "Más" abre el menú lateral completo del layout común.
    document.getElementById('nav-inferior-mas')?.addEventListener('click', () => document.querySelector('[data-abrir-menu]')?.click());
    // El boton de "siguiente paso" cambia de destino: se resuelve al hacer clic.
    document.getElementById('sp-boton')?.addEventListener('click', (e) => {
      const pagina = e.currentTarget.dataset.page;
      e.stopImmediatePropagation();
      if (pagina) { e.preventDefault(); navegar(pagina); }
    }, true);
    const celebro = novedadesDesdeUltimaVisita();
    // La bienvenida para elegir la meta se muestra una vez al dia como maximo si la pospone.
    let pospuesta = '';
    try { pospuesta = localStorage.getItem('msea-meta-pospuesta') || ''; } catch (e) { pospuesta = ''; }
    const hoyTexto = new Date().toDateString();
    if (!celebro && (ESTUDIANTE.metaDiaria === null || ESTUDIANTE.metaDiaria === undefined) && pospuesta !== hoyTexto) {
      setTimeout(() => {
        elegirMeta(true);
        try { localStorage.setItem('msea-meta-pospuesta', hoyTexto); } catch (e) { /* opcional */ }
      }, 500);
    }
  }

  function actualizarSubtitulo(pagina) {
    const sub = document.getElementById('greeting-sub');
    if (!sub) return;
    const n = pendientes().length;
    const textos = {
      inicio: n ? `Tienes ${n} tarea(s) por entregar.` : 'No tienes tareas pendientes.',
      tareas: `Tienes ${TAREAS.length} tarea(s) asignadas.`,
      cursos: `Estás inscrito en ${CURSOS.length} curso(s).`,
      ejercicios: 'Practica y suma XP. El servidor mide el tiempo.',
      progreso: `Llevas ${numero(ESTUDIANTE.puntos)} XP en total.`,
      ranking: ESTUDIANTE.rankingPos ? `Estás en el puesto #${ESTUDIANTE.rankingPos}.` : 'Aún no apareces en el ranking.',
      logros: `Tienes ${ESTUDIANTE.logrosDesbloqueados} logro(s) desbloqueado(s).`,
      mensajes: 'Comunícate con tu profesor.',
      perfil: 'Administra tu información.',
    };
    sub.textContent = textos[pagina] || textos.inicio;
  }

  /* ---------------------------------------------------------- navegacion */

  function initNavegacion() {
    document.getElementById('sidebar-profile-btn')?.addEventListener('click', () => navegar('perfil'));
    document.querySelectorAll('.nav-item, .nav-link-btn').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (!el.dataset.page) return; // enlace normal (por ejemplo, a un curso)
        e.preventDefault();
        navegar(el.dataset.page);
      });
    });
    document.querySelectorAll('[data-filtro-tarea]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-filtro-tarea]').forEach((b) => { b.classList.toggle('active', b === btn); b.setAttribute('aria-pressed', String(b === btn)); });
        filtroTareas = btn.dataset.filtroTarea;
        renderTareasCompletas();
      });
    });
  }

  function navegar(pagina, { enfocar = true } = {}) {
    const destino = document.getElementById(`view-${pagina}`);
    if (!pagina || !destino) return;
    document.querySelectorAll('.nav-item').forEach((i) => {
      const activo = i.dataset.page === pagina;
      i.classList.toggle('active', activo);
      if (activo) i.setAttribute('aria-current', 'page'); else i.removeAttribute('aria-current');
    });
    document.querySelectorAll('.page-view').forEach((v) => v.classList.toggle('active', v === destino));
    document.querySelectorAll('.nav-inferior [data-page]').forEach((a) => a.classList.toggle('activo', a.dataset.page === pagina));
    history.replaceState(null, '', `#${pagina}`);
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    actualizarSubtitulo(pagina);
    UI.anunciarVista(destino, document.querySelector(`.nav-item[data-page="${pagina}"]`)?.textContent, { base: 'Mi panel · MSEA', enfocar });
    if (pagina === 'mensajes') api('/mensajes/leidos', { json: {} }).catch(() => {});
  }

  /* ---------------------------------------------------------- notificaciones */

  function initNotificaciones() {
    const btn = document.getElementById('notif-btn');
    const panel = document.getElementById('notif-panel');
    renderNotificaciones();

    btn?.addEventListener('click', (e) => {
      e.stopPropagation();
      const abierto = panel?.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(Boolean(abierto)));
    });
    document.addEventListener('click', (e) => {
      if (!document.getElementById('notif-wrapper')?.contains(e.target)) panel?.classList.remove('open');
    });
    document.getElementById('notif-clear-btn')?.addEventListener('click', async (e) => {
      try {
        await conCarga(e.currentTarget, api('/dashboard-estudiante/notificaciones/leidas', { json: {} }));
        NOTIFICACIONES.forEach((n) => { n.leida = true; });
        renderNotificaciones();
        toast('Notificaciones marcadas como leídas.');
      } catch (err) {
        toast(err.message);
      }
    });
  }

  function renderNotificaciones() {
    const ul = document.getElementById('notif-list');
    if (!ul) return;
    document.getElementById('notif-dot')?.classList.toggle('hidden', !NOTIFICACIONES.some((n) => !n.leida));

    if (!NOTIFICACIONES.length) {
      ul.innerHTML = `<li>${vacio('No hay notificaciones nuevas.', '', 'campana')}</li>`;
      return;
    }
    ul.innerHTML = NOTIFICACIONES.map((n) => `
      <li class="notif-item ${n.leida ? 'leida' : ''}">
        <span class="notif-item-icon">${icon(UI.iconoTipo(n.tipo))}</span>
        <div class="notif-item-body">
          <p class="notif-item-texto">${esc(n.texto)}</p>
          <p class="notif-item-tiempo">${esc(n.tiempo)}</p>
        </div>
      </li>`).join('');
  }

  /* ---------------------------------------------------------- tareas */

  function etiquetaEstado(tarea) {
    const estado = tarea.urgencia === 'vencida' && tarea.estado === 'pendiente' ? 'vencida' : tarea.estado;
    const texto = estado === 'vencida' ? 'Vencida' : (ESTADOS[tarea.estado] || 'Pendiente');
    return `<span class="estado-tag ${esc(estado)}">${esc(texto)}</span>`;
  }

  function renderTareasInicio() {
    const ul = document.getElementById('tareas-list');
    if (!ul) return;
    const items = pendientes().slice(0, 3);
    if (!items.length) {
      ul.innerHTML = `<li>${vacio('Aún no tienes tareas pendientes.', 'Cuando tu profesor asigne una tarea aparecerá aquí.', 'tareas')}</li>`;
      return;
    }
    ul.innerHTML = items.map((t) => `
      <li><button type="button" class="tarea-item" data-id="${Number(t.id)}">
        <span class="icon-badge">${icon(t.escalaUrl ? 'afinacion' : (t.grupal ? 'grupo' : 'tareas'))}</span>
        <span class="tarea-info">
          <span class="tarea-nombre">${esc(t.nombre)}</span>
          <span class="tarea-meta">${esc(t.profesor)} - vence ${esc(t.vence)}</span>
        </span>
        ${etiquetaEstado(t)}
      </button></li>`).join('');
    ul.querySelectorAll('.tarea-item').forEach((boton) => {
      const tarea = TAREAS.find((t) => Number(t.id) === Number(boton.dataset.id));
      const url = tarea && tarea.escalaUrl ? UI.safeUrl(tarea.escalaUrl) : null;
      boton.addEventListener('click', () => (url ? window.location.assign(url) : navegar('tareas')));
    });
  }

  function renderTareasCompletas() {
    const grid = document.getElementById('tareas-full-grid');
    if (!grid) return;
    const items = TAREAS.filter((t) => filtroTareas === 'todas' || t.estado === filtroTareas);

    if (!items.length) {
      grid.innerHTML = vacio(
        filtroTareas === 'todas' ? 'Aún no tienes tareas asignadas.' : 'No hay tareas en este estado.',
        '', 'tareas');
      return;
    }

    grid.innerHTML = items.map((t) => {
      const material = t.materialUrl && UI.safeUrl(t.materialUrl)
        ? `<a class="tcf-link" href="${esc(UI.safeUrl(t.materialUrl))}" target="_blank" rel="noopener">${icon('archivo')} Material: ${esc(t.materialNombre)}</a>` : '';
      const grabacion = t.archivoUrl && UI.safeUrl(t.archivoUrl)
        ? `<audio controls preload="none" src="${esc(UI.safeUrl(t.archivoUrl))}"></audio>` : '';
      const nota = t.estado === 'calificada'
        ? `<div class="tcf-nota">${icon('estrella', 'icon-gold')} <strong>${esc(Math.round(t.calificacion))}/100</strong></div>` : '';
      const observacion = t.comentarioProfesor
        ? `<div class="teacher-feedback"><strong>Observación del profesor:</strong> ${esc(t.comentarioProfesor)}</div>` : '';
      let accion = t.puedeEntregar
        ? `<button class="btn-msea small btn-entregar" data-id="${Number(t.id)}" type="button">${icon('subir')} ${t.estado === 'pendiente' ? 'Entregar' : t.estado === 'devuelta' ? 'Corregir y reenviar' : 'Reemplazar entrega'}</button>`
        : '';
      if (t.escalaUrl && UI.safeUrl(t.escalaUrl)) {
        // Tarea de escala: se realiza en la práctica guiada (nota objetivo, pentagrama y metrónomo).
        const texto = !t.puedeEntregar ? 'Practicar otra vez' : t.estado === 'pendiente' ? 'Realizar tarea' : t.estado === 'devuelta' ? 'Corregir y volver a tocar' : 'Volver a tocar';
        accion = `<a class="btn-msea small" href="${esc(UI.safeUrl(t.escalaUrl))}">${icon('afinacion')} ${texto}</a>`;
      }

      return `
        <article class="tarea-card-full ${esc(t.urgencia)}">
          <div class="tcf-top">
            <span class="icon-badge">${icon(t.grupal ? 'grupo' : 'tareas')}</span>
            <div class="tcf-info">
              <p class="tcf-nombre">${esc(t.nombre)}</p>
              <p class="tcf-profe">${esc(t.profesor)}${t.grupal ? ' - tarea de elenco' : ''}</p>
            </div>
            ${etiquetaEstado(t)}
          </div>
          ${t.escalaResumen ? `<p class="tcf-desc tcf-escala">${icon('afinacion')} ${esc(t.escalaResumen)}</p>` : ''}
          ${t.desc ? `<p class="tcf-desc">${esc(t.desc)}</p>` : ''}
          ${material}
          <div class="tcf-meta">
            <span class="tcf-chip">${icon('calendario')} ${esc(t.vence)}</span>
            <span class="tcf-chip">+${Number(t.xp)} XP</span>
          </div>
          ${nota}${observacion}${grabacion}
          ${accion}
        </article>`;
    }).join('');

    grid.querySelectorAll('.btn-entregar').forEach((btn) => {
      btn.addEventListener('click', () => abrirEntrega(TAREAS.find((t) => Number(t.id) === Number(btn.dataset.id))));
    });
  }

  /* ---------------------------------------------------------- entrega con grabacion o archivo */

  let tareaActual = null;
  let grabacion = null;

  let dialogoEntrega = null;
  function abrirEntrega(tarea) {
    if (!tarea) return;
    dialogoEntrega ||= UI.dialogo(document.getElementById('modal-entrega'), { alCerrar: () => detenerGrabacion() });
    tareaActual = tarea;
    grabacion = null;
    const form = document.getElementById('form-entrega');
    form?.reset();
    UI.mostrarErrores(form, {});
    document.getElementById('entrega-tarea-titulo').textContent = tarea.nombre;
    document.getElementById('entrega-devuelta').textContent = tarea.estado === 'devuelta' && tarea.comentarioProfesor
      ? `Tu profesor pidió: ${tarea.comentarioProfesor}` : '';
    const audio = document.getElementById('recorder-audio');
    if (audio) { audio.hidden = true; audio.removeAttribute('src'); }
    document.getElementById('recorder-time').textContent = '00:00';
    dialogoEntrega.abrir();
  }

  function cerrarEntrega() {
    detenerGrabacion();
    if (dialogoEntrega) dialogoEntrega.cerrar();
    else document.getElementById('modal-entrega')?.classList.add('hidden');
  }

  let recorder = null;
  let streamGrabacion = null;
  let relojGrabacion = null;

  function detenerGrabacion() {
    if (recorder && recorder.state !== 'inactive') recorder.stop();
    streamGrabacion?.getTracks().forEach((t) => t.stop());
    clearInterval(relojGrabacion);
    document.getElementById('recorder')?.classList.remove('recording');
  }

  function initEntrega() {
    document.getElementById('btn-entrega-cancel')?.addEventListener('click', cerrarEntrega);

    const btnGrabar = document.getElementById('btn-grabar');
    if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
      btnGrabar?.setAttribute('disabled', 'disabled');
      btnGrabar?.setAttribute('title', 'Tu navegador no permite grabar. Sube un archivo de audio.');
    }

    btnGrabar?.addEventListener('click', async () => {
      if (recorder && recorder.state === 'recording') {
        detenerGrabacion();
        btnGrabar.innerHTML = `${icon('microfono')} Grabar de nuevo`;
        return;
      }
      try {
        streamGrabacion = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch (e) {
        toast('No se pudo usar el micrófono. Revisa los permisos o sube un archivo.');
        return;
      }
      const partes = [];
      recorder = new MediaRecorder(streamGrabacion);
      recorder.addEventListener('dataavailable', (e) => { if (e.data.size) partes.push(e.data); });
      recorder.addEventListener('stop', () => {
        grabacion = new Blob(partes, { type: recorder.mimeType || 'audio/webm' });
        const audio = document.getElementById('recorder-audio');
        audio.src = URL.createObjectURL(grabacion);
        audio.hidden = false;
        document.getElementById('entrega-archivo').value = '';
      });
      recorder.start();
      const inicio = Date.now();
      document.getElementById('recorder')?.classList.add('recording');
      relojGrabacion = setInterval(() => {
        const s = Math.floor((Date.now() - inicio) / 1000);
        document.getElementById('recorder-time').textContent = `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
        if (s >= 600) { detenerGrabacion(); toast('La grabación se detuvo a los 10 minutos.'); }
      }, 500);
      btnGrabar.innerHTML = `${icon('detener')} Detener grabación`;
    });

    document.getElementById('entrega-archivo')?.addEventListener('change', () => { grabacion = null; });

    document.getElementById('form-entrega')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!tareaActual) return;
      const form = e.currentTarget;
      const datos = new FormData();
      datos.append('comentario_estudiante', document.getElementById('entrega-comentario').value.trim());
      const archivo = document.getElementById('entrega-archivo').files[0];
      if (grabacion) {
        const ext = (grabacion.type.includes('ogg') ? 'ogg' : grabacion.type.includes('mp4') ? 'm4a' : 'webm');
        datos.append('archivo', grabacion, `grabacion.${ext}`);
      } else if (archivo) {
        datos.append('archivo', archivo);
      }

      const barra = document.getElementById('entrega-progreso');
      barra?.classList.remove('hidden');
      const relleno = barra?.querySelector('span');
      try {
        const r = await conCarga(document.getElementById('btn-entrega-submit'), api(`/dashboard-estudiante/tareas/${Number(tareaActual.id)}/entregar`, {
          form: datos,
          onProgress: (pct) => { if (relleno) relleno.style.width = `${pct}%`; },
        }));
        tareaActual.estado = 'entregada';
        tareaActual.archivoNombre = r.entrega?.archivo_nombre || tareaActual.archivoNombre;
        cerrarEntrega();
        renderTareasCompletas();
        renderTareasInicio();
        renderResumen();
        toast('Tarea entregada. Tu profesor recibirá una notificación.');
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      } finally {
        barra?.classList.add('hidden');
        if (relleno) relleno.style.width = '0';
      }
    });
  }

  /* ---------------------------------------------------------- cursos */

  function renderCursos() {
    const grid = document.getElementById('cursos-grid');
    const destacado = document.getElementById('curso-destacado');
    const tarjeta = (c) => `
      <article class="card curso-card">
        <div class="curso-card-head">
          <span class="icon-badge">${icon(UI.iconoInstrumento(c.instrumento))}</span>
          <div>
            <p class="tcf-nombre">${esc(c.titulo)}</p>
            <p class="tcf-profesor">${esc(c.instrumento || 'Todos los instrumentos')} - ${esc(c.nivel || '')}</p>
          </div>
        </div>
        <div class="upload-progress" role="progressbar" aria-valuenow="${Number(c.progreso)}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso del curso"><span data-ancho="${Number(c.progreso)}"></span></div>
        <p class="tool-muted">${Number(c.completadas)} de ${Number(c.lecciones)} lecciones (${Number(c.progreso)}%)</p>
        <a class="btn-msea small" href="${esc(UI.safeUrl(c.url))}">${c.progreso >= 100 ? 'Repasar' : c.completadas ? 'Continuar' : 'Empezar'} ${icon('flecha-derecha')}</a>
      </article>`;

    if (grid) grid.innerHTML = CURSOS.length ? CURSOS.map(tarjeta).join('') : vacio('Todavía no estás inscrito en cursos.', 'El director o tu profesor te inscribirán.', 'cursos');
    if (destacado) {
      const c = CURSOS.find((x) => x.progreso < 100) || CURSOS[0];
      destacado.innerHTML = c ? `
        <div class="curso-destacado">
          <div class="cd-cabecera">
            <span class="icon-badge">${icon(UI.iconoInstrumento(c.instrumento))}</span>
            <div><p class="cd-titulo">${esc(c.titulo)}</p><p class="cd-sub">${esc(c.instrumento || 'Todos los instrumentos')}${c.nivel ? ` · ${esc(c.nivel)}` : ''}</p></div>
          </div>
          <div class="tn-barra" role="progressbar" aria-valuenow="${Number(c.progreso)}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso del curso"><div class="tn-relleno" data-ancho="${Number(c.progreso)}"></div></div>
          <p class="cd-pie">${Number(c.completadas)} de ${Number(c.lecciones)} lecciones · ${Number(c.progreso)}%</p>
          <a class="btn-msea" href="${esc(UI.safeUrl(c.url))}">${c.progreso >= 100 ? 'Repasar' : c.completadas ? 'Continuar' : 'Empezar'} ${icon('flecha-derecha')}</a>
        </div>` : vacio('Sin cursos por ahora.', 'Tu profesor o la dirección te inscribirán.', 'cursos');
    }
  }

  /* ---------------------------------------------------------- ranking */

  function renderRanking() {
    const mini = document.getElementById('ranking-list');
    const full = document.getElementById('ranking-full-list');
    const podio = document.getElementById('podio');
    const medalla = (pos) => (pos === 1 ? 'gold' : pos === 2 ? 'silver' : pos === 3 ? 'bronze' : '');
    const filaMini = (j, i) => {
      const pos = Number(j.posicion || i + 1);
      return `
      <li class="ranking-item ${j.esYo ? 'es-yo' : ''}">
        <span class="rank-pos ${medalla(pos)}">#${pos}</span>
        <span class="rank-avatar">${icon(pos <= 3 ? 'ranking' : 'musica', pos <= 3 ? 'icon-gold' : 'icon-green')}</span>
        <div class="rank-info">
          <p class="rank-name">${esc(j.nombre)}</p>
          <p class="rank-pts">${numero(j.puntos)} XP</p>
        </div>
        ${j.esYo ? '<span class="rank-badge-yo">Tú</span>' : ''}
      </li>`;
    };
    const filaFull = (j, i) => {
      const pos = Number(j.posicion || i + 1);
      return `
      <li class="ranking-full-item ${j.esYo ? 'es-yo' : ''}">
        <span class="rf-pos">${pos}</span>
        <span class="rf-avatar">${icon('musica', 'icon-green')}</span>
        <div class="rf-info">
          <p class="rf-nombre">${esc(j.nombre)} ${j.esYo ? '<span class="rank-badge-yo">Tú</span>' : ''}</p>
        </div>
        <span class="rf-pts">${numero(j.puntos)} XP</span>
      </li>`;
    };

    const vacioRanking = vacio('Tu grupo aún no tiene puntos.', 'Practica para aparecer aquí.', 'ranking');
    if (mini) mini.innerHTML = RANKING.length ? RANKING.slice(0, 5).map(filaMini).join('') : `<li>${vacioRanking}</li>`;
    if (full) full.innerHTML = RANKING.length ? RANKING.map(filaFull).join('') : `<li>${vacioRanking}</li>`;
    if (podio) {
      podio.innerHTML = RANKING.slice(0, 3).map((j, i) => `
        <div class="podio-item">
          <span class="podio-avatar">${icon('ranking', 'icon-xl icon-gold')}</span>
          <p class="podio-nombre">${esc(j.nombre)}</p>
          <p class="podio-pts">${numero(j.puntos)} XP</p>
          <div class="podio-bloque">${Number(j.posicion || i + 1)}</div>
        </div>`).join('');
    }
  }

  /* ---------------------------------------------------------- logros */

  function renderLogros() {
    const tarjeta = (l) => `
      <div class="logro-item ${l.bloqueado ? 'bloqueado' : ''}" title="${esc(l.desc)}">
        <span class="logro-emoji">${icon(l.bloqueado ? 'candado' : 'logro', l.bloqueado ? 'icon-lg icon-muted' : 'icon-lg icon-gold')}</span>
        <span class="logro-nombre">${esc(l.nombre)}</span>
        <span class="logro-desc">${esc(l.desc || '')}</span>
        ${l.xpBonus ? `<span class="logro-bono">+${Number(l.xpBonus)} XP</span>` : ''}
        ${l.fecha ? `<span class="logro-fecha">${esc(l.fecha)}</span>` : ''}
      </div>`;
    const desbloqueados = LOGROS.filter((l) => !l.bloqueado);
    const grid = document.getElementById('logros-grid');
    const full = document.getElementById('logros-grid-full');

    // En el inicio: los ultimos logros y los proximos por conseguir (motiva a seguir).
    const proximos = LOGROS.filter((l) => l.bloqueado).slice(0, Math.max(2, 6 - Math.min(4, desbloqueados.length)));
    const inicio = [...desbloqueados.slice(-4).reverse(), ...proximos].slice(0, 6);
    if (grid) grid.innerHTML = inicio.length ? inicio.map(tarjeta).join('') : vacio('Todavía no has desbloqueado logros.', 'Practica varios días seguidos para conseguir el primero.', 'logro');
    if (full) full.innerHTML = LOGROS.length ? LOGROS.map(tarjeta).join('') : vacio('No hay logros configurados.', '', 'logro');

    setText('logros-desbloqueados', desbloqueados.length);
    setText('logros-bloqueados', LOGROS.length - desbloqueados.length);
    setText('logros-porcentaje', `${LOGROS.length ? Math.round((desbloqueados.length * 100) / LOGROS.length) : 0}%`);
  }

  /* ---------------------------------------------------------- progreso */

  function renderProgreso() {
    const stats = document.getElementById('progreso-stats');
    if (stats) {
      const calificadas = TAREAS.filter((t) => t.estado === 'calificada').length;
      const items = [
        ['estrella', 'XP total', `${numero(ESTUDIANTE.puntos)} XP`, ESTUDIANTE.xpMax ? (ESTUDIANTE.xp / ESTUDIANTE.xpMax) * 100 : 0],
        ['tareas', 'Tareas calificadas', `${calificadas} / ${TAREAS.length}`, TAREAS.length ? (calificadas * 100) / TAREAS.length : 0],
        ['racha', 'Racha actual', `${ESTUDIANTE.racha} días (mejor: ${ESTUDIANTE.mejorRacha || 0})`, Math.min(100, (ESTUDIANTE.racha / 7) * 100)],
        ['musica', 'Prácticas registradas', String(ESTUDIANTE.ejerciciosHechos), Math.min(100, ESTUDIANTE.ejerciciosHechos * 5)],
      ];
      stats.innerHTML = items.map(([ic, label, val, pct]) => `
        <div class="pstat-item">
          <span class="pstat-icon">${icon(ic)}</span>
          <div class="pstat-info">
            <p class="pstat-label">${esc(label)}</p>
            <div class="pstat-bar-wrap"><div class="pstat-bar" data-ancho="${Math.round(pct)}"></div></div>
          </div>
          <span class="pstat-val">${esc(val)}</span>
        </div>`).join('');
    }

    const ul = document.getElementById('actividad-list');
    if (ul) {
      ul.innerHTML = ACTIVIDAD.length ? ACTIVIDAD.map((a) => `
        <li class="actividad-item">
          <span class="act-icon">${icon(UI.iconoTipo(a.tipo))}</span>
          <div class="act-info"><p class="act-texto">${esc(a.texto)}</p><p class="act-tiempo">${esc(a.tiempo)}</p></div>
          <span class="act-xp">${esc(a.xp)}</span>
        </li>`).join('') : `<li>${vacio('Todavía no has completado ejercicios.', 'Tu historial de XP aparecerá aquí.', 'actividad')}</li>`;
    }
  }

  /* ---------------------------------------------------------- practica: sesiones medidas por el servidor */

  async function iniciarSesion(tipo, idEjercicio = null) {
    const r = await api('/dashboard-estudiante/practicas', { json: { tipo, id_ejercicio: idEjercicio } });
    return r.id_practica;
  }

  async function finalizarSesion(id, metricas = {}) {
    const r = await api(`/dashboard-estudiante/practicas/${Number(id)}/finalizar`, { json: metricas });
    sumarXp(r.xp, 'practica');
    aplicarNovedades(r, document.activeElement);
    const min = Math.floor(r.duracion_segundos / 60);
    const seg = r.duracion_segundos % 60;
    toast(r.xp ? `Práctica registrada: ${min} min ${seg} s, +${r.xp} XP.` : r.message);
    return r;
  }

  function sumarXp(xp, fuente) {
    const n = Number(xp || 0);
    if (!n) return;
    ESTUDIANTE.puntos = Number(ESTUDIANTE.puntos) + n;
    ESTUDIANTE.xp = Number(ESTUDIANTE.xp) + n;
    while (ESTUDIANTE.xp >= ESTUDIANTE.xpMax) { ESTUDIANTE.xp -= ESTUDIANTE.xpMax; ESTUDIANTE.nivel = Number(ESTUDIANTE.nivel) + 1; }
    if (fuente === 'practica') ESTUDIANTE.practicaXpHoy = Number(ESTUDIANTE.practicaXpHoy) + n;
    ESTUDIANTE.ejerciciosHechos = Number(ESTUDIANTE.ejerciciosHechos) + (fuente === 'practica' ? 1 : 0);
    renderResumen();
  }

  function renderEjercicios() {
    const grid = document.getElementById('ejercicios-grid');
    if (!grid) return;
    if (!EJERCICIOS.length) {
      grid.innerHTML = vacio('No hay ejercicios en la biblioteca todavía.', 'Usa las herramientas de arriba para practicar.', 'ejercicios');
      return;
    }
    grid.innerHTML = EJERCICIOS.map((e) => `
      <article class="ejercicio-card-full">
        <span class="ecf-icon icon-badge">${icon(UI.iconoTipo(e.tipo))}</span>
        <p class="ecf-nombre">${esc(e.nombre)}</p>
        <p class="ecf-desc">${esc(e.desc)}</p>
        <div class="ecf-chips"><span class="ecf-chip">${esc(e.categoria)}</span><span class="ecf-chip">${esc(e.dificultad)}</span></div>
        <button class="btn-msea small btn-practicar-ejercicio" type="button" data-id="${Number(e.id)}" data-tipo="${esc(e.tipo)}">${icon('play')} Practicar</button>
      </article>`).join('');

    grid.querySelectorAll('.btn-practicar-ejercicio').forEach((btn) => {
      let id = null;
      btn.addEventListener('click', async () => {
        try {
          if (!id) {
            id = await conCarga(btn, iniciarSesion(btn.dataset.tipo, Number(btn.dataset.id)));
            btn.innerHTML = `${icon('detener')} Terminar práctica`;
            btn.classList.add('secondary');
          } else {
            await conCarga(btn, finalizarSesion(id));
            id = null;
            btn.innerHTML = `${icon('play')} Practicar`;
            btn.classList.remove('secondary');
          }
        } catch (err) {
          toast(err.message);
        }
      });
    });
  }

  function initPracticaLibre() {
    const btn = document.getElementById('btn-practica');
    const reloj = document.getElementById('practica-reloj');
    let id = null;
    let inicio = 0;
    let timer = null;

    btn?.addEventListener('click', async () => {
      try {
        if (!id) {
          id = await conCarga(btn, iniciarSesion(document.getElementById('practica-tipo').value));
          inicio = Date.now();
          timer = setInterval(() => {
            const s = Math.floor((Date.now() - inicio) / 1000);
            reloj.textContent = `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
          }, 500);
          btn.textContent = 'Terminar práctica';
        } else {
          clearInterval(timer);
          await conCarga(btn, finalizarSesion(id));
          id = null;
          reloj.textContent = '00:00';
          btn.textContent = 'Iniciar práctica';
        }
      } catch (err) {
        toast(err.message);
      }
    });
  }

  /* ---------------------------------------------------------- afinador (deteccion real por autocorrelacion) */

  const CUERDAS = {
    violin: [['Sol3', 196.0], ['Re4', 293.66], ['La4', 440.0], ['Mi5', 659.25]],
    viola: [['Do3', 130.81], ['Sol3', 196.0], ['Re4', 293.66], ['La4', 440.0]],
    violonchelo: [['Do2', 65.41], ['Sol2', 98.0], ['Re3', 146.83], ['La3', 220.0]],
    contrabajo: [['Mi1', 41.2], ['La1', 55.0], ['Re2', 73.42], ['Sol2', 98.0]],
  };

  function initAfinador() {
    const btn = document.getElementById('btn-tuner');
    const select = document.getElementById('tuner-instrumento');
    const notaEl = document.getElementById('tuner-note');
    const freqEl = document.getElementById('tuner-frequency');
    const aguja = document.getElementById('tuner-needle');
    const cuerdasEl = document.getElementById('tuner-cuerdas');

    const instrumentoInicial = UI.iconoInstrumento(ESTUDIANTE.instrumento);
    if (select && CUERDAS[instrumentoInicial]) select.value = instrumentoInicial;

    const pintarCuerdas = (activa) => {
      if (!cuerdasEl) return;
      cuerdasEl.innerHTML = (CUERDAS[select.value] || []).map(([n]) => `<span class="${n === activa ? 'activa' : ''}">${esc(n)}</span>`).join('');
    };
    pintarCuerdas(null);
    select?.addEventListener('change', () => pintarCuerdas(null));

    let ctx = null;
    let analyser = null;
    let stream = null;
    let activo = false;
    let idSesion = null;
    let lecturas = [];
    let mejor = null;

    if (!navigator.mediaDevices?.getUserMedia) {
      btn?.setAttribute('disabled', 'disabled');
      if (freqEl) freqEl.textContent = 'Tu navegador no permite usar el micrófono.';
    }

    btn?.addEventListener('click', async () => {
      if (activo) {
        activo = false;
        stream?.getTracks().forEach((t) => t.stop());
        ctx?.close();
        btn.textContent = 'Iniciar afinador';
        if (idSesion) {
          const metricas = mejor ? {
            instrumento: select.value,
            nota_objetivo: mejor.objetivo,
            nota_detectada: mejor.nota,
            frecuencia: Number(mejor.frecuencia.toFixed(2)),
            desviacion_cents: Number(mejor.cents.toFixed(1)),
          } : { instrumento: select.value };
          finalizarSesion(idSesion, metricas).catch((e) => toast(e.message));
          idSesion = null;
        }
        return;
      }

      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false } });
      } catch (e) {
        if (freqEl) freqEl.textContent = e && e.name === 'NotFoundError'
          ? 'No encontramos un micrófono en este dispositivo.'
          : 'Permiso de micrófono denegado. Actívalo en el navegador para afinar.';
        return;
      }
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      analyser = ctx.createAnalyser();
      analyser.fftSize = 8192;
      ctx.createMediaStreamSource(stream).connect(analyser);
      activo = true;
      lecturas = [];
      mejor = null;
      btn.textContent = 'Detener afinador';
      try { idSesion = await iniciarSesion('afinacion'); } catch (e) { toast(e.message); }
      bucle();
    });

    function bucle() {
      if (!activo) return;
      const buffer = new Float32Array(analyser.fftSize);
      analyser.getFloatTimeDomainData(buffer);
      const lectura = autoCorrelacion(buffer, ctx.sampleRate);

      if (lectura.estado === 'silencio') {
        freqEl.textContent = 'No se escucha sonido. Toca una cuerda cerca del micrófono.';
        lecturas = [];
      } else if (lectura.estado === 'ruido') {
        freqEl.textContent = 'Hay mucho ruido o la señal no es clara.';
        lecturas = [];
      } else {
        lecturas.push(lectura.frecuencia);
        if (lecturas.length > 7) lecturas.shift();
        // Solo se informa cuando 5 lecturas seguidas coinciden (senal estable).
        if (lecturas.length >= 5) {
          const ordenadas = [...lecturas].sort((a, b) => a - b);
          const mediana = ordenadas[Math.floor(ordenadas.length / 2)];
          const estable = ordenadas.every((f) => Math.abs(1200 * Math.log2(f / mediana)) < 25);
          if (estable) mostrarNota(mediana);
          else freqEl.textContent = 'Sosteniendo la nota...';
        }
      }
      requestAnimationFrame(bucle);
    }

    function mostrarNota(frecuencia) {
      const objetivo = (CUERDAS[select.value] || []).reduce((a, b) => (Math.abs(Math.log2(frecuencia / b[1])) < Math.abs(Math.log2(frecuencia / a[1])) ? b : a));
      const cents = 1200 * Math.log2(frecuencia / objetivo[1]);
      const nota = nombreNota(frecuencia);
      notaEl.textContent = nota;
      const estado = Math.abs(cents) <= 5 ? 'Afinada' : cents > 0 ? 'Muy aguda: afloja' : 'Muy grave: tensa';
      freqEl.textContent = `${frecuencia.toFixed(1)} Hz - cuerda ${objetivo[0]} - ${cents > 0 ? '+' : ''}${cents.toFixed(0)} cents - ${estado}`;
      if (aguja) aguja.style.transform = `translateX(${Math.max(-48, Math.min(48, cents))}px)`;
      pintarCuerdas(objetivo[0]);
      if (Math.abs(cents) < 50 && (!mejor || Math.abs(cents) < Math.abs(mejor.cents))) {
        mejor = { frecuencia, cents, nota, objetivo: objetivo[0] };
      }
    }
  }

  /** Estimacion de frecuencia fundamental (autocorrelacion normalizada), 35-1100 Hz. */
  function autoCorrelacion(buffer, sampleRate) {
    let rms = 0;
    for (let i = 0; i < buffer.length; i++) rms += buffer[i] * buffer[i];
    rms = Math.sqrt(rms / buffer.length);
    if (rms < 0.008) return { estado: 'silencio' };

    const minOffset = Math.floor(sampleRate / 1100);
    const maxOffset = Math.min(Math.floor(sampleRate / 35), Math.floor(buffer.length / 2));
    const n = buffer.length - maxOffset;
    let mejorOffset = -1;
    let mejorCorr = 0;
    let anterior = 1;
    let subiendo = false;

    for (let offset = minOffset; offset <= maxOffset; offset++) {
      let suma = 0;
      let e1 = 0;
      let e2 = 0;
      for (let i = 0; i < n; i++) {
        suma += buffer[i] * buffer[i + offset];
        e1 += buffer[i] * buffer[i];
        e2 += buffer[i + offset] * buffer[i + offset];
      }
      const corr = suma / Math.sqrt(e1 * e2 || 1);
      if (corr > anterior) subiendo = true;
      if (subiendo && corr > mejorCorr) {
        mejorCorr = corr;
        mejorOffset = offset;
      }
      if (subiendo && corr < anterior && mejorCorr > 0.92) break; // primer pico claro
      anterior = corr;
    }

    if (mejorCorr < 0.85 || mejorOffset < 0) return { estado: 'ruido' };
    return { estado: 'ok', frecuencia: sampleRate / mejorOffset };
  }

  function nombreNota(frecuencia) {
    const nombres = ['Do', 'Do#', 'Re', 'Re#', 'Mi', 'Fa', 'Fa#', 'Sol', 'Sol#', 'La', 'La#', 'Si'];
    const midi = Math.round(12 * Math.log2(frecuencia / 440) + 69);
    return `${nombres[((midi % 12) + 12) % 12]}${Math.floor(midi / 12) - 1}`;
  }

  /* ---------------------------------------------------------- metronomo con medicion de golpes */

  function initMetronomo() {
    const btn = document.getElementById('btn-metro');
    const input = document.getElementById('metro-bpm');
    const texto = document.getElementById('metro-bpm-text');
    const golpe = document.getElementById('btn-golpe');
    const resultado = document.getElementById('ritmo-resultado');
    const puntos = [...document.querySelectorAll('#beat-dots span')];
    let ctx = null;
    let timer = null;
    let pulso = 0;
    let tiempos = [];
    let desvios = [];
    let idSesion = null;

    input?.addEventListener('input', () => {
      texto.textContent = input.value;
      if (timer) reiniciar();
    });

    const marcar = () => {
      if (!timer || !tiempos.length) return;
      const ahora = performance.now();
      const intervalo = 60000 / Number(input.value);
      const cercano = tiempos.reduce((a, b) => (Math.abs(b - ahora) < Math.abs(a - ahora) ? b : a));
      const desvio = ahora - cercano;
      if (Math.abs(desvio) <= intervalo / 2) desvios.push(desvio);
      resultado.textContent = `${desvios.length} golpes - último a ${desvio > 0 ? '+' : ''}${Math.round(desvio)} ms del pulso`;
    };
    golpe?.addEventListener('pointerdown', marcar);
    document.addEventListener('keydown', (e) => {
      if (e.code === 'Space' && timer && document.getElementById('view-ejercicios')?.classList.contains('active')) {
        e.preventDefault();
        marcar();
      }
    });

    btn?.addEventListener('click', async () => {
      if (timer) {
        clearInterval(timer);
        timer = null;
        btn.textContent = 'Iniciar metrónomo';
        golpe.disabled = true;
        puntos.forEach((d) => d.classList.remove('active'));

        const intervalo = 60000 / Number(input.value);
        let precision = null;
        if (desvios.length >= 8) {
          const dentro = desvios.filter((d) => Math.abs(d) <= intervalo * 0.12).length;
          precision = Math.round((dentro * 100) / desvios.length);
          const promedio = Math.round(desvios.reduce((a, b) => a + Math.abs(b), 0) / desvios.length);
          resultado.textContent = `Precisión medida en tu navegador: ${precision}% (desvío medio ${promedio} ms, ${desvios.length} golpes). Dato no verificado por el servidor.`;
        } else {
          resultado.textContent = desvios.length
            ? 'Pocos golpes para medir la precisión (mínimo 8). Solo se registró el tiempo de práctica.'
            : 'No marcaste golpes: se registró solo el tiempo de práctica, sin precisión.';
        }

        if (idSesion) {
          finalizarSesion(idSesion, { bpm: Number(input.value), patron: '4/4', precision, golpes: desvios.length }).catch((e) => toast(e.message));
          idSesion = null;
        }
        return;
      }

      desvios = [];
      try { idSesion = await iniciarSesion('ritmo'); } catch (e) { toast(e.message); }
      reiniciar();
      btn.textContent = 'Detener metrónomo';
      golpe.disabled = false;
      resultado.textContent = 'Marca cada pulso que escuches.';
    });

    function reiniciar() {
      clearInterval(timer);
      pulso = 0;
      tiempos = [];
      tic();
      timer = setInterval(tic, 60000 / Number(input.value));
    }

    function tic() {
      ctx ||= new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.frequency.value = pulso % 4 === 0 ? 1100 : 760;
      gain.gain.value = 0.08;
      osc.connect(gain).connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.04);
      tiempos.push(performance.now());
      if (tiempos.length > 8) tiempos.shift();
      puntos.forEach((d, i) => d.classList.toggle('active', i === pulso % 4));
      pulso += 1;
    }
  }

  /* ---------------------------------------------------------- teoria (evaluada en el servidor) */

  function renderTeoria() {
    const caja = document.getElementById('theory-box');
    if (!caja) return;
    const pendientesTeoria = TEORIA.filter((p) => !p.resuelta);
    setText('teoria-contador', `${TEORIA.length - pendientesTeoria.length}/${TEORIA.length}`);
    const pregunta = pendientesTeoria[0];

    if (!pregunta) {
      caja.innerHTML = TEORIA.length
        ? vacio('Respondiste todas las preguntas disponibles.', 'Pronto habrá preguntas nuevas.', 'check-circulo')
        : vacio('Aún no hay preguntas de teoría.', '', 'teoria');
      return;
    }

    caja.innerHTML = `
      <p class="tool-question">${esc(pregunta.pregunta)}</p>
      <div class="theory-options">${pregunta.opciones.map((o, i) => `<button type="button" data-i="${i}">${esc(o)}</button>`).join('')}</div>
      <p class="tool-muted">+${Number(pregunta.xp)} XP si aciertas al primer intento.</p>`;

    caja.querySelectorAll('button').forEach((boton) => {
      boton.addEventListener('click', async () => {
        const respuesta = pregunta.opciones[Number(boton.dataset.i)];
        caja.querySelectorAll('button').forEach((b) => { b.disabled = true; });
        try {
          const r = await api(`/dashboard-estudiante/teoria/${Number(pregunta.id)}/responder`, { json: { respuesta } });
          boton.classList.add(r.correcta ? 'ok' : 'bad');
          if (r.correcta) {
            pregunta.resuelta = true;
            sumarXp(r.xp, 'teoria');
            aplicarNovedades({ ...r, xp: r.xp }, boton);
            toast(r.xp ? `Correcto: +${r.xp} XP${r.primer_intento ? '' : ' (menos XP por no ser el primer intento)'}` : 'Correcto.');
            setTimeout(renderTeoria, 1200);
          } else {
            toast(`No era esa. La respuesta correcta es: ${r.respuesta_correcta}. ¡Recuérdala para la próxima!`, 4000);
            setTimeout(() => caja.querySelectorAll('button').forEach((b) => { b.disabled = false; b.classList.remove('bad'); }), 1500);
          }
        } catch (err) {
          toast(err.message);
          caja.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        }
      });
    });
  }

  /* ---------------------------------------------------------- mensajes */

  function renderMensajes() {
    const ul = document.getElementById('mensajes-lista');
    const select = document.getElementById('mensaje-destino');
    if (select) {
      select.innerHTML = CONTACTOS.length
        ? CONTACTOS.map((c) => `<option value="${Number(c.id)}">${esc(c.nombre)} (${esc(c.rol)})</option>`).join('')
        : '<option value="">Aún no tienes profesor asignado</option>';
      select.disabled = !CONTACTOS.length;
    }
    if (!ul) return;
    ul.innerHTML = MENSAJES.length ? MENSAJES.map((m) => `
      <li class="mensaje-item ${m.enviado ? 'enviado' : ''} ${!m.leido && !m.enviado ? 'no-leido' : ''}">
        <p class="mensaje-meta">${m.enviado ? 'Para' : 'De'} <strong>${esc(m.con)}</strong> - ${esc(UI.fecha(m.fecha))}</p>
        ${m.asunto ? `<p class="mensaje-asunto">${esc(m.asunto)}</p>` : ''}
        <p class="mensaje-texto">${esc(m.contenido)}</p>
      </li>`).join('') : `<li>${vacio('No tienes mensajes.', '', 'mensaje')}</li>`;
  }

  function initMensajes() {
    document.getElementById('form-mensaje')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const datos = Object.fromEntries(new FormData(form));
      try {
        await conCarga(form.querySelector('button[type="submit"]'), api('/mensajes', { json: datos }));
        const contacto = CONTACTOS.find((c) => String(c.id) === String(datos.id_destinatario));
        MENSAJES.unshift({ enviado: true, con: contacto?.nombre || '', asunto: datos.asunto, contenido: datos.contenido, fecha: new Date().toISOString(), leido: true });
        form.reset();
        renderMensajes();
        toast('Mensaje enviado.');
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      }
    });
  }

  /* ---------------------------------------------------------- perfil */

  function aplicarAvatar(url) {
    const seguro = UI.safeUrl(url);
    [['s-avatar-img', 's-avatar-fallback'], ['header-avatar-img', 'header-avatar-fallback'], ['perfil-avatar-img', 'perfil-avatar-fallback']].forEach(([imgId, fbId]) => {
      const img = document.getElementById(imgId);
      const fb = document.getElementById(fbId);
      if (!img) return;
      if (seguro) {
        img.src = seguro;
        img.hidden = false;
        img.classList.add('loaded');
        if (fb) fb.style.display = 'none';
      } else {
        // Sin foto: se oculta la imagen (evita el icono de imagen rota) y se muestra el icono.
        img.hidden = true;
        img.classList.remove('loaded');
        if (fb) fb.style.display = '';
      }
    });
  }

  function llenarPerfil() {
    setText('info-nombre', ESTUDIANTE.nombreCompleto);
    setText('info-email', ESTUDIANTE.email);
    setText('info-ci', ESTUDIANTE.ci);
    setText('info-celular', ESTUDIANTE.celular);
    setText('info-direccion', ESTUDIANTE.direccion);
    setText('info-fecha-nacimiento', ESTUDIANTE.fechaNacimiento ? String(ESTUDIANTE.fechaNacimiento).slice(0, 10) : '');
    setText('info-instrumento', ESTUDIANTE.instrumento);
    setText('info-nivel', ESTUDIANTE.seccion);
    setText('info-sede', ESTUDIANTE.sede);
    setText('info-profesor', ESTUDIANTE.profesor);
    setText('info-miembro-desde', ESTUDIANTE.miembroDesde);

    const valores = {
      'edit-nombres': ESTUDIANTE.nombre, 'edit-apellido-paterno': ESTUDIANTE.apellidoPaterno, 'edit-apellido-materno': ESTUDIANTE.apellidoMaterno,
      'edit-email': ESTUDIANTE.email, 'edit-celular': ESTUDIANTE.celular,
    };
    Object.entries(valores).forEach(([id, v]) => { const el = document.getElementById(id); if (el) el.value = v || ''; });
    document.getElementById('campo-contrasena-actual')?.classList.add('hidden');
  }

  async function redimensionar(file, max = 512, calidad = 0.82) {
    if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) throw new Error('Elige una imagen PNG, JPG o WEBP.');
    const dataUrl = await new Promise((resolve, reject) => {
      const lector = new FileReader();
      lector.onload = (ev) => resolve(ev.target.result);
      lector.onerror = reject;
      lector.readAsDataURL(file);
    });
    const img = await new Promise((resolve, reject) => {
      const i = new Image();
      i.onload = () => resolve(i);
      i.onerror = () => reject(new Error('No se pudo leer la imagen.'));
      i.src = dataUrl;
    });
    const escala = Math.min(1, max / Math.max(img.width, img.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.width * escala);
    canvas.height = Math.round(img.height * escala);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', calidad);
  }

  async function guardarPerfil(extra = {}) {
    const form = document.getElementById('info-edit');
    const datos = Object.fromEntries(new FormData(form));
    if (!datos.contrasena_actual) delete datos.contrasena_actual;
    const r = await api('/dashboard-estudiante/perfil', { json: { ...datos, ...extra } });
    Object.assign(ESTUDIANTE, r.estudiante || {});
    llenarPerfil();
    renderResumen();
    return r;
  }

  function initPerfil() {
    llenarPerfil();

    const form = document.getElementById('info-edit');
    const vista = document.getElementById('info-view');
    const btnEditar = document.getElementById('btn-edit-info');

    btnEditar?.addEventListener('click', () => {
      vista?.classList.add('hidden');
      form?.classList.remove('hidden');
      document.getElementById('edit-nombres')?.focus();
    });
    document.getElementById('btn-cancel-info')?.addEventListener('click', () => {
      form?.classList.add('hidden');
      vista?.classList.remove('hidden');
      llenarPerfil();
      UI.mostrarErrores(form, {});
    });
    document.getElementById('edit-email')?.addEventListener('input', (e) => {
      const cambia = e.target.value.trim().toLowerCase() !== String(ESTUDIANTE.email || '').toLowerCase();
      document.getElementById('campo-contrasena-actual')?.classList.toggle('hidden', !cambia);
    });
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await conCarga(document.getElementById('btn-save-info'), guardarPerfil());
        form.classList.add('hidden');
        vista?.classList.remove('hidden');
        UI.mostrarErrores(form, {});
        toast('Información guardada.');
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      }
    });

    const entrada = document.getElementById('avatar-file-input');
    const modal = document.getElementById('modal-foto');
    const vistaPrevia = document.getElementById('modal-preview');
    let pendiente = null;
    const dialogoFoto = UI.dialogo(modal, { alCerrar: () => { if (entrada) entrada.value = ''; } });

    document.getElementById('perfil-avatar-overlay')?.addEventListener('click', () => entrada?.click());
    entrada?.addEventListener('change', async () => {
      const archivo = entrada.files[0];
      if (!archivo) return;
      try {
        pendiente = await redimensionar(archivo);
        vistaPrevia.src = pendiente;
        dialogoFoto.abrir();
      } catch (err) {
        toast(err.message);
      }
    });
    document.getElementById('btn-foto-confirm')?.addEventListener('click', async (e) => {
      try {
        await conCarga(e.currentTarget, guardarPerfil({ foto: pendiente }));
        toast('Foto de perfil actualizada.');
      } catch (err) {
        toast(err.message);
      }
      dialogoFoto.cerrar();
    });
    document.getElementById('btn-foto-cancel')?.addEventListener('click', () => dialogoFoto.cerrar());

    document.getElementById('form-contrasena')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.currentTarget;
      try {
        const r = await conCarga(f.querySelector('button[type="submit"]'), api('/dashboard-estudiante/contrasena', { json: Object.fromEntries(new FormData(f)) }));
        f.reset();
        UI.mostrarErrores(f, {});
        toast(r.message);
      } catch (err) {
        UI.mostrarErrores(f, err.errors);
        toast(err.message);
      }
    });
  }
})();
