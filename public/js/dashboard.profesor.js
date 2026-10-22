/* ============================================================
   MSEA - Panel del profesor
   - Datos solo del servidor; sin datos fijos de demostracion.
   - Todo texto se escapa (MSEA_UI.esc) antes de usar innerHTML.
   - Tareas individuales o por elenco, entregas con audio, calificar,
     devolver para correccion, perfil y mensajes.
   ============================================================ */
(function () {
  'use strict';

  const UI = window.MSEA_UI;
  const { esc, icon, vacio, api, conCarga, toast } = UI;
  const lista = (v) => (Array.isArray(v) ? v : []);

  const PROFESOR = Object.assign({ nombre: '', primerNombre: '', email: '', especialidad: '', foto: null, totalAlumnos: 0, tareasRevision: 0, tareasCompletadas: 0 }, window.MSEA_PROFESOR || {});
  const ALUMNOS = lista(window.MSEA_ALUMNOS);
  let TAREAS = lista(window.MSEA_TAREAS);
  const ELENCOS = lista(window.MSEA_ELENCOS);
  const EJERCICIOS = lista(window.MSEA_EJERCICIOS);
  const TIPOS = lista(window.MSEA_TIPOS);
  // Biblioteca técnica por instrumento: { violin: [...], viola: [...], violonchelo: [...], contrabajo: [...] }.
  const CATALOGO = Array.isArray(window.MSEA_ESCALAS) ? { violin: window.MSEA_ESCALAS } : (window.MSEA_ESCALAS || {});
  const INSTRUMENTOS = { violin: 'Violín', viola: 'Viola', violonchelo: 'Violonchelo', contrabajo: 'Contrabajo' };
  const CLAVES = { violin: 'clave de Sol', viola: 'clave de Do', violonchelo: 'clave de Fa', contrabajo: 'clave de Fa' };
  const escalasDe = (inst) => lista(CATALOGO[inst]);
  const TECNICA = window.MSEA_TECNICA || {};
  const tecnicaDe = (inst) => lista(TECNICA[inst]);
  const tecnicaPorId = (id) => Object.values(TECNICA).flat().find((e) => Number(e.id) === Number(id));
  let catBiblioteca = 'escala';
  let instForm = 'violin';
  let instBiblioteca = 'violin';
  const NOTIFICACIONES = lista(window.MSEA_NOTIFICACIONES);
  let MENSAJES = lista(window.MSEA_MENSAJES);
  let CONTACTOS = [];
  let filtro = 'todas';
  let tareaEditando = null;

  document.addEventListener('DOMContentLoaded', () => {
    aplicarProfesor();
    initNavegacion();
    initNotificaciones();
    initModales();
    initFormTarea();
    initEscalas();
    initTecnica();
    initBiblioteca();
    initPerfil();
    initMensajes();
    renderTodo();
    const inicial = (window.location.hash || '').replace('#', '');
    if (inicial) navegar(inicial, { enfocar: false });
    window.addEventListener('hashchange', () => navegar(window.location.hash.replace('#', '')));
  });

  function renderTodo() {
    renderResumen();
    renderEntregasPendientes();
    renderAlumnosTop();
    renderAtencion();
    renderPracticaGrupo();
    renderAlumnos();
    renderTareas();
    renderEjercicios();
    renderBiblioteca();
    renderProgresoGrupal();
    renderMensajes();
  }

  /* ---------------------------------------------------------- cabecera */

  function setText(id, v) {
    const el = document.getElementById(id);
    if (el) el.textContent = v === null || v === undefined ? '' : String(v);
  }

  function aplicarProfesor() {
    const hora = new Date().getHours();
    const saludo = hora >= 6 && hora < 12 ? 'Buenos días' : hora >= 12 && hora < 19 ? 'Buenas tardes' : 'Buenas noches';
    setText('greeting-title', `${saludo}, prof. ${PROFESOR.primerNombre || PROFESOR.nombre}`);
    setText('s-name', `Prof. ${PROFESOR.nombre}`);
    setText('s-role', PROFESOR.especialidad || 'Sin especialidad');
    setText('pi-especialidad', `Especialidad: ${PROFESOR.especialidad || 'sin registrar'}`);
    const foto = UI.safeUrl(PROFESOR.foto);
    ['sidebar-avatar', 'header-avatar', 'perfil-avatar'].forEach((id) => {
      const img = document.getElementById(id);
      if (!img) return;
      if (foto) { img.src = foto; img.hidden = false; img.classList.add('loaded'); img.nextElementSibling?.classList.add('hidden'); } else { img.hidden = true; }
    });
  }

  function renderResumen() {
    const porRevisar = TAREAS.reduce((s, t) => s + Number(t.entregas_por_revisar || 0), 0);
    const calificadas = TAREAS.reduce((s, t) => s + Number(t.entregas_calificadas || 0), 0);
    setText('s-total-alumnos', ALUMNOS.length);
    setText('s-tareas-pendientes', porRevisar);
    setText('s-tareas-total', TAREAS.length);
    setText('st-alumnos', ALUMNOS.length);
    setText('st-por-revisar', porRevisar);
    setText('st-completadas', calificadas);
    setText('st-elencos', ELENCOS.length);
    setText('greeting-sub', porRevisar ? `Tienes ${porRevisar} entrega(s) por revisar.` : 'No tienes entregas pendientes de revisión.');
    const badge = document.getElementById('badge-tareas');
    if (badge) { badge.textContent = String(porRevisar); badge.classList.toggle('hidden', porRevisar === 0); }
  }

  /* ---------------------------------------------------------- navegacion */

  function initNavegacion() {
    document.querySelectorAll('.nav-item, .nav-trigger').forEach((el) => el.addEventListener('click', (e) => {
      e.preventDefault();
      navegar(el.dataset.page);
    }));

    document.querySelectorAll('.filtro-btn[data-filtro]').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn[data-filtro]').forEach((x) => x.classList.toggle('active', x === b));
      filtro = b.dataset.filtro;
      renderTareas();
    }));
    document.getElementById('search-alumnos')?.addEventListener('input', (e) => renderAlumnos(e.target.value.toLowerCase().trim()));
  }

  function navegar(pagina, { enfocar = true } = {}) {
    const destino = document.getElementById(`page-${pagina}`);
    if (!destino) return;
    document.querySelectorAll('.page-content').forEach((p) => p.classList.toggle('active', p === destino));
    document.querySelectorAll('.nav-item').forEach((n) => {
      const activo = n.dataset.page === pagina;
      n.classList.toggle('active', activo);
      if (activo) n.setAttribute('aria-current', 'page'); else n.removeAttribute('aria-current');
    });
    document.getElementById('contenido')?.scrollTo?.(0, 0);
    window.scrollTo(0, 0);
    history.replaceState(null, '', `#${pagina}`);
    UI.anunciarVista(destino, document.querySelector(`.nav-item[data-page="${pagina}"]`)?.textContent, { base: 'Panel del profesor · MSEA', enfocar });
    if (pagina === 'mensajes') cargarContactos();
  }

  /* ---------------------------------------------------------- notificaciones */

  function initNotificaciones() {
    const btn = document.getElementById('notif-btn');
    const panel = document.getElementById('notif-panel');
    const ul = document.getElementById('notif-list');
    document.getElementById('notif-dot')?.classList.toggle('hidden', !NOTIFICACIONES.some((n) => !n.leida));
    if (ul) {
      ul.innerHTML = NOTIFICACIONES.length ? NOTIFICACIONES.map((n) => `
        <li class="notif-item ${n.leida ? 'leida' : ''}">
          <span class="notif-item-icon">${icon(UI.iconoTipo(n.tipo))}</span>
          <div class="notif-item-body"><p class="notif-item-texto"><strong>${esc(n.titulo)}</strong> ${esc(n.texto)}</p><p class="notif-item-tiempo">${esc(UI.fecha(n.fecha))}</p></div>
        </li>`).join('') : `<li>${vacio('No hay notificaciones nuevas.', '', 'campana')}</li>`;
    }
    btn?.addEventListener('click', (e) => {
      e.stopPropagation();
      btn.setAttribute('aria-expanded', String(Boolean(panel?.classList.toggle('open'))));
    });
    document.addEventListener('click', (e) => { if (!panel?.contains(e.target) && !btn?.contains(e.target)) panel?.classList.remove('open'); });
  }

  /* ---------------------------------------------------------- modales */

  let focoAntesDelModal = null;
  function abrirModal(id) {
    if (!document.querySelector('.modal.open')) focoAntesDelModal = document.activeElement;
    document.getElementById('modal-overlay')?.classList.add('active');
    const m = document.getElementById(id);
    m?.classList.add('open');
    m?.querySelector('input, select, textarea, button')?.focus();
  }

  function cerrarModales() {
    document.getElementById('modal-overlay')?.classList.remove('active');
    const habiaAbierto = document.querySelector('.modal.open');
    document.querySelectorAll('.modal.open').forEach((m) => m.classList.remove('open'));
    if (habiaAbierto) focoAntesDelModal?.focus?.();
  }

  function initModales() {
    document.querySelectorAll('[data-cerrar]').forEach((b) => b.addEventListener('click', cerrarModales));
    document.getElementById('modal-overlay')?.addEventListener('click', cerrarModales);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { cerrarModales(); return; }
      const abierto = document.querySelector('.modal.open');
      if (abierto) UI.atraparFoco(abierto, e);
    });
  }

  /* ---------------------------------------------------------- inicio */

  function renderEntregasPendientes() {
    const ul = document.getElementById('entregas-list');
    if (!ul) return;
    const conEntregas = TAREAS.filter((t) => Number(t.entregas_por_revisar) > 0);
    if (!conEntregas.length) {
      ul.innerHTML = `<li>${vacio('Sin entregas pendientes.', 'Cuando tus alumnos entreguen, aparecerán aquí.', 'subir')}</li>`;
      return;
    }
    ul.innerHTML = conEntregas.slice(0, 5).map((t) => `
      <li class="entrega-item">
        <span class="entrega-avatar">${icon(t.destino === 'elenco' ? 'grupo' : 'tareas')}</span>
        <div class="entrega-info"><p class="entrega-alumno">${esc(t.alumno)}</p><p class="entrega-tarea">${esc(t.titulo)}</p></div>
        <span class="estado-tag entregada">${Number(t.entregas_por_revisar)} por revisar</span>
        <button class="btn-msea small" type="button" data-entregas="${Number(t.id)}">Revisar</button>
      </li>`).join('');
    ul.querySelectorAll('[data-entregas]').forEach((b) => b.addEventListener('click', () => abrirEntregas(Number(b.dataset.entregas))));
  }

  function renderAlumnosTop() {
    const ul = document.getElementById('alumnos-top-list');
    if (!ul) return;
    // Constancia de la semana (minutos), no XP acumulado: premia el habito reciente.
    const top = [...ALUMNOS].sort((a, b) => (b.minutosSemana - a.minutosSemana) || (b.puntos - a.puntos)).slice(0, 5);
    ul.innerHTML = top.length ? top.map((a, i) => `
      <li class="alumno-top-item">
        <span class="at-pos">${i + 1}</span>
        <span class="at-avatar">${icon(UI.iconoInstrumento(a.instrumento), 'icon-green')}</span>
        <div class="at-info"><p class="at-nombre">${esc(a.nombre)}</p><p class="at-racha">${icon('racha')} ${Number(a.racha)} días - ${esc(a.nivel)}</p></div>
        <span class="at-pts">${Number(a.minutosSemana || 0)} min</span>
      </li>`).join('') : `<li>${vacio('No tienes alumnos asignados.', 'El director asigna alumnos a cada profesor.', 'usuarios')}</li>`;
  }

  function renderAtencion() {
    const ul = document.getElementById('atencion-list');
    if (!ul) return;
    const lista = ALUMNOS
      .filter((a) => a.diasSinPracticar === null || a.diasSinPracticar === undefined || Number(a.diasSinPracticar) >= 5)
      .sort((a, b) => (b.diasSinPracticar ?? 999) - (a.diasSinPracticar ?? 999))
      .slice(0, 5);
    if (!ALUMNOS.length) { ul.innerHTML = `<li>${vacio('No tienes alumnos asignados.', '', 'usuarios')}</li>`; return; }
    if (!lista.length) { ul.innerHTML = `<li>${vacio('¡Todo tu grupo practicó esta semana!', 'Nadie lleva 5 días o más sin practicar.', 'check-circulo')}</li>`; return; }
    ul.innerHTML = lista.map((a) => {
      const motivo = a.diasSinPracticar === null || a.diasSinPracticar === undefined ? 'Aún no registró prácticas' : `${Number(a.diasSinPracticar)} días sin practicar`;
      return `<li class="atencion-item">
        <span class="at-avatar">${icon(UI.iconoInstrumento(a.instrumento), 'icon-green')}</span>
        <div class="at-info"><p class="at-nombre">${esc(a.nombre)}</p><p class="atencion-motivo">${icon('alerta')} ${esc(motivo)}</p></div>
        <button type="button" class="btn-msea small secondary" data-animar="${Number(a.idUsuario)}" data-nombre="${esc(a.nombre)}">${icon('mensaje')} Escribir</button>
      </li>`;
    }).join('');
    ul.querySelectorAll('[data-animar]').forEach((b) => b.addEventListener('click', () => escribirA(Number(b.dataset.animar), b.dataset.nombre)));
  }

  /** Abre Mensajes con el alumno elegido y un borrador amable listo para editar. */
  async function escribirA(idUsuario, nombre) {
    navegar('mensajes');
    await cargarContactos();
    const select = document.getElementById('mensaje-destino');
    if (select && [...select.options].some((o) => Number(o.value) === idUsuario)) select.value = String(idUsuario);
    const form = document.getElementById('form-mensaje');
    const asunto = form?.querySelector('[name="asunto"]');
    const texto = form?.querySelector('[name="contenido"]');
    const primero = String(nombre || '').split(' ')[0];
    if (asunto && !asunto.value) asunto.value = '¡Te extrañamos en la práctica!';
    if (texto && !texto.value) texto.value = `Hola ${primero}, noté que hace unos días no practicas. Con 10 minutos hoy ya mantienes el ritmo. ¿Te ayudo con algo?`;
    texto?.focus();
  }

  function renderPracticaGrupo() {
    const cont = document.getElementById('grupo-barras');
    if (!cont) return;
    const datos = Array.isArray(window.MSEA_PRACTICA_GRUPO) ? window.MSEA_PRACTICA_GRUPO : [];
    const max = Math.max(1, ...datos.map((d) => Number(d.minutos)));
    const total = datos.reduce((t, d) => t + Number(d.minutos), 0);
    setText('grupo-total', `${total} min en total`);
    cont.innerHTML = datos.map((d) => `
      <div class="gb-col ${d.hoy ? 'hoy' : ''}" title="${Number(d.minutos)} min · ${Number(d.alumnos)} alumno(s)">
        <span class="gb-valor">${Number(d.minutos) ? Number(d.minutos) : ''}</span>
        <div class="gb-barra"><span data-alto="${Math.max(4, Math.round((Number(d.minutos) / max) * 100))}"></span></div>
        <span class="gb-dia">${esc(d.dia)}</span>
      </div>`).join('');
  }

  /* ---------------------------------------------------------- alumnos */

  function renderAlumnos(busqueda = '') {
    const grid = document.getElementById('alumnos-grid');
    if (!grid) return;
    const items = ALUMNOS.filter((a) => !busqueda || `${a.nombre} ${a.instrumento} ${a.elenco || ''}`.toLowerCase().includes(busqueda));
    if (!items.length) {
      grid.innerHTML = vacio(ALUMNOS.length ? 'No se encontraron alumnos.' : 'No tienes alumnos asignados.', '', 'usuarios');
      return;
    }
    grid.innerHTML = items.map((a) => `
      <article class="card alumno-card">
        <div class="ac-avatar">${UI.safeUrl(a.foto) ? `<img src="${esc(UI.safeUrl(a.foto))}" alt="" />` : icon(UI.iconoInstrumento(a.instrumento), 'icon-xl icon-green')}</div>
        <p class="ac-nombre">${esc(a.nombre)}</p>
        <p class="ac-instrumento">${esc(a.instrumento)}${a.elenco ? ` - ${esc(a.elenco)}` : ''}</p>
        <span class="ac-nivel">${esc(a.nivel)}</span>
        <div class="ac-xp-track" role="progressbar" aria-label="XP del nivel" aria-valuenow="${Math.round((a.xp / a.xpMax) * 100)}" aria-valuemin="0" aria-valuemax="100"><div class="ac-xp-fill" data-ancho="${Math.round((a.xp / a.xpMax) * 100)}"></div></div>
        <div class="ac-stats"><span>${icon('estrella', 'icon-gold')} ${Number(a.puntos)} XP</span><span>${icon('racha')} ${Number(a.racha)} días</span><span>${icon('check')} ${Number(a.completadas)}</span></div>
        <button class="btn-msea small secondary" type="button" data-progreso="${Number(a.id)}">Ver progreso</button>
      </article>`).join('');
    grid.querySelectorAll('[data-progreso]').forEach((b) => b.addEventListener('click', () => abrirProgreso(Number(b.dataset.progreso))));
  }

  async function abrirProgreso(idEstudiante) {
    setText('progreso-alumno-nombre', 'Cargando...');
    document.getElementById('progreso-resumen').innerHTML = '';
    document.getElementById('progreso-historial-lista').innerHTML = '';
    abrirModal('modal-progreso-alumno');
    try {
      const r = await api(`/dashboard-profesor/alumnos/${idEstudiante}/progreso`);
      const e = r.estudiante;
      setText('progreso-alumno-nombre', e.nombre);
      const datos = [
        ['estrella', 'XP total', e.xp], ['graduacion', 'Nivel', e.nivel], ['racha', 'Racha', `${e.racha} días`],
        ['reloj', 'Minutos de práctica', e.totalMinutos], ['tareas', 'Tareas calificadas', e.ejerciciosHechos],
        ['cursos', 'Lecciones completadas', e.leccionesCompletadas], ['afinacion', 'Sesiones de afinador', e.afinacionesHechas],
        ['ritmo', 'Precisión rítmica (navegador)', e.precisionRitmo ? `${e.precisionRitmo}%` : 'Sin medir'],
        ['alerta', 'Respuestas de teoría incorrectas', e.teoriaFallos],
      ];
      document.getElementById('progreso-resumen').innerHTML = datos.map(([ic, label, val]) => `
        <div class="progreso-mini"><span class="icon-badge">${icon(ic)}</span><div><strong>${esc(val)}</strong><span>${esc(label)}</span></div></div>`).join('');
      document.getElementById('progreso-historial-lista').innerHTML = r.historial.length ? r.historial.map((h) => `
        <li class="notif-item"><div class="notif-item-body"><p class="notif-item-texto"><strong>${esc(h.tipo)}</strong>: ${esc(h.detalle)}</p><p class="notif-item-tiempo">${esc(h.fecha_texto)}</p></div></li>`).join('')
        : `<li>${vacio('Este alumno aún no registra práctica.', '', 'actividad')}</li>`;
    } catch (err) {
      setText('progreso-alumno-nombre', 'No se pudo cargar');
      toast(err.message);
    }
  }

  /* ---------------------------------------------------------- tareas */

  function renderTareas() {
    const tbody = document.getElementById('tareas-tbody');
    if (!tbody) return;
    const items = TAREAS.filter((t) => filtro === 'todas'
      || (filtro === 'por_revisar' && Number(t.entregas_por_revisar) > 0)
      || t.destino === filtro);

    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="5">${vacio(TAREAS.length ? 'No hay tareas con este filtro.' : 'Aún no creaste tareas.', 'Usa "Nueva tarea" para asignar la primera.', 'tareas')}</td></tr>`;
      return;
    }
    tbody.innerHTML = items.map((t) => `
      <tr>
        <td><strong>${esc(t.titulo)}</strong>${t.material_url ? `<br><a class="tcf-link" href="${esc(UI.safeUrl(t.material_url))}" target="_blank" rel="noopener">${icon('archivo')} ${esc(t.material_nombre)}</a>` : ''}</td>
        <td>${icon(t.destino === 'elenco' ? 'grupo' : 'perfil')} ${esc(t.alumno)}</td>
        <td>${esc(t.limite ? String(t.limite).slice(0, 10) : 'Sin fecha')}</td>
        <td>${Number(t.entregas_total)} recibidas${Number(t.entregas_por_revisar) ? `<br><span class="estado-tag entregada">${Number(t.entregas_por_revisar)} por revisar</span>` : ''}</td>
        <td><div class="acciones">
          <button class="btn-msea small" type="button" data-entregas="${Number(t.id)}" ${Number(t.entregas_total) ? '' : 'disabled'}>Entregas</button>
          <button class="btn-msea small secondary" type="button" data-editar="${Number(t.id)}" aria-label="Editar tarea">${icon('editar')}</button>
          <button class="btn-msea small danger" type="button" data-eliminar="${Number(t.id)}" ${Number(t.entregas_total) ? 'disabled title="Tiene entregas: se conserva para no perder el trabajo de los alumnos"' : ''} aria-label="Eliminar tarea">${icon('borrar')}</button>
        </div></td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-entregas]').forEach((b) => b.addEventListener('click', () => abrirEntregas(Number(b.dataset.entregas))));
    tbody.querySelectorAll('[data-editar]').forEach((b) => b.addEventListener('click', () => abrirFormTarea(TAREAS.find((t) => t.id === Number(b.dataset.editar)))));
    tbody.querySelectorAll('[data-eliminar]').forEach((b) => b.addEventListener('click', () => eliminarTarea(Number(b.dataset.eliminar), b)));
  }

  async function eliminarTarea(id, boton) {
    const tarea = TAREAS.find((t) => t.id === id);
    if (!tarea || !window.confirm(`¿Eliminar la tarea "${tarea.titulo}"? Esta acción no se puede deshacer.`)) return;
    try {
      await conCarga(boton, api(`/dashboard-profesor/tareas/${id}`, { method: 'DELETE' }));
      TAREAS = TAREAS.filter((t) => t.id !== id);
      renderTodo();
      toast('Tarea eliminada.');
    } catch (err) {
      toast(err.message);
    }
  }

  function initFormTarea() {
    const form = document.getElementById('form-tarea');
    const alumno = document.getElementById('tarea-alumno');
    const elenco = document.getElementById('tarea-elenco');
    const tipo = document.getElementById('tarea-tipo');
    alumno.innerHTML = ALUMNOS.length ? ALUMNOS.map((a) => `<option value="${Number(a.id)}">${esc(a.nombre)} - ${esc(a.instrumento)}</option>`).join('') : '<option value="">No tienes alumnos asignados</option>';
    elenco.innerHTML = ELENCOS.length ? ELENCOS.map((e) => `<option value="${Number(e.id)}">${esc(e.nombre)} (${Number(e.alumnos)} alumnos tuyos)</option>`).join('') : '<option value="">Tus alumnos no tienen elenco</option>';
    tipo.innerHTML = (TIPOS.length ? TIPOS : ['Repertorio']).map((t) => `<option ${t === 'Repertorio' ? 'selected' : ''}>${esc(t)}</option>`).join('');

    form.querySelectorAll('input[name="destino"]').forEach((r) => r.addEventListener('change', () => {
      const porElenco = form.querySelector('input[name="destino"]:checked').value === 'elenco';
      document.getElementById('campo-alumno').classList.toggle('hidden', porElenco);
      document.getElementById('campo-elenco').classList.toggle('hidden', !porElenco);
    }));

    document.getElementById('btn-nueva-tarea')?.addEventListener('click', () => abrirFormTarea(null));
    document.getElementById('btn-nueva-tarea-lg')?.addEventListener('click', () => abrirFormTarea(null));

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const datos = new FormData(form);
      const porElenco = datos.get('destino') === 'elenco';
      datos.delete('destino');
      const esEscala = datos.get('clase_tarea') === 'escala';
      const esTecnica = datos.get('clase_tarea') === 'tecnica';
      ['clase_tarea', 'esc_direccion', 'esc_ritmo_modo', 'esc_octavas', 'tec_modo'].forEach((k) => datos.delete(k));
      if (esTecnica) {
        const config = configTecnica();
        if (!config) return;
        datos.delete('tipo');
        datos.set('id_biblioteca', document.getElementById('tec-ejercicio').value);
        datos.set('config_escala', JSON.stringify(config));
      } else if (esEscala) {
        const config = configEscala();
        if (!config) return;
        datos.delete('tipo');
        datos.set('config_escala', JSON.stringify(config));
      } else {
        datos.delete('id_biblioteca');
        if (!String(datos.get('titulo') || '').trim()) {
          UI.mostrarErrores(form, { titulo: ['Escribe el título de la tarea.'] });
          form.titulo.focus();
          return;
        }
      }
      if (porElenco) datos.delete('id_estudiante'); else datos.delete('id_elenco');
      if (!datos.get('material') || !datos.get('material').size) datos.delete('material');
      if (!datos.get('fecha_limite')) datos.delete('fecha_limite');

      const barra = document.getElementById('tarea-progreso');
      barra.classList.remove('hidden');
      const url = tareaEditando ? `/dashboard-profesor/tareas/${tareaEditando.id}` : '/dashboard-profesor/tareas';
      try {
        await conCarga(document.getElementById('btn-guardar-tarea'), api(url, {
          form: datos,
          onProgress: (p) => { barra.querySelector('span').style.width = `${p}%`; },
        }));
        UI.toastTrasRecargar(tareaEditando ? 'Tarea actualizada.' : 'Tarea creada y notificada a los alumnos.');
        window.location.hash = 'tareas';
        window.location.reload();
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      } finally {
        barra.classList.add('hidden');
        barra.querySelector('span').style.width = '0';
      }
    });
  }

  function abrirFormTarea(tarea, opciones = {}) {
    tareaEditando = tarea || null;
    const form = document.getElementById('form-tarea');
    form.reset();
    UI.mostrarErrores(form, {});
    setText('modal-tarea-titulo', tarea ? 'Editar tarea' : 'Nueva tarea');
    const hoy = new Date().toISOString().slice(0, 10);
    const fecha = document.getElementById('tarea-fecha');
    fecha.min = tarea ? '' : hoy;
    if (tarea) {
      form.titulo.value = tarea.titulo;
      form.descripcion.value = tarea.descripcion || '';
      form.xp_recompensa.value = tarea.xp;
      fecha.value = tarea.limite ? String(tarea.limite).slice(0, 10) : '';
      const porElenco = tarea.destino === 'elenco';
      form.querySelector(`input[name="destino"][value="${porElenco ? 'elenco' : 'estudiante'}"]`).checked = true;
      if (porElenco) form.id_elenco.value = String(tarea.id_elenco); else form.id_estudiante.value = String(tarea.id_estudiante);
    }
    form.querySelector('input[name="destino"]:checked').dispatchEvent(new Event('change'));
    const escalaId = tarea ? tarea.id_biblioteca : (opciones.escalaId || opciones.tecnicaId || null);
    prepararEscala(escalaId, tarea ? tarea.config_escala : null);
    abrirModal('modal-tarea');
  }

  /* ---------------------------------------------------------- tareas de escala (biblioteca técnica) */

  const FIGURAS = {
    redonda: { pulsos: 4, glifo: '\uE1D2', nombre: ['redonda', 'redondas'] },
    blanca_puntillo: { pulsos: 3, glifo: '\uE1D3\uE1E7', nombre: ['blanca con puntillo', 'blancas con puntillo'] },
    blanca: { pulsos: 2, glifo: '\uE1D3', nombre: ['blanca', 'blancas'] },
    negra_puntillo: { pulsos: 1.5, glifo: '\uE1D5\uE1E7', nombre: ['negra con puntillo', 'negras con puntillo'] },
    negra: { pulsos: 1, glifo: '\uE1D5', nombre: ['negra', 'negras'] },
    corchea_puntillo: { pulsos: 0.75, glifo: '\uE1D7\uE1E7', nombre: ['corchea con puntillo', 'corcheas con puntillo'] },
    corchea: { pulsos: 0.5, glifo: '\uE1D7', nombre: ['corchea', 'corcheas'] },
    semicorchea: { pulsos: 0.25, glifo: '\uE1D9', nombre: ['semicorchea', 'semicorcheas'] },
  };
  const PRESETS = [
    ['redonda', 1], ['blanca', 2], ['negra', 4], ['corchea', 8], ['semicorchea', 16], ['negra', 1], ['negra', 2], ['negra', 3],
  ];
  const CELULAS = [
    ['negra_puntillo', 'corchea'],
    ['corchea', 'semicorchea', 'semicorchea'],
    ['semicorchea', 'semicorchea', 'corchea'],
    ['corchea_puntillo', 'semicorchea'],
    ['corchea', 'corchea', 'negra'],
  ];
  const TOLERANCIA = { facil: 15, intermedio: 10, dificil: 5 };
  let celdas = [['negra']];
  let celdaActiva = 0;
  let filtroBiblioteca = 'todas';

  const glifo = (f) => `<span class="smufl" aria-hidden="true">${FIGURAS[f].glifo}</span>`;
  const pulsosDe = (figs) => figs.reduce((a, f) => a + FIGURAS[f].pulsos, 0);
  const textoPulsos = (n) => `${String(Math.round(n * 100) / 100).replace('.', ',')} ${n === 1 ? 'pulso' : 'pulsos'}`;
  const escalaPorId = (id) => Object.values(CATALOGO).flat().find((e) => Number(e.id) === Number(id));

  /** Instrumento (clave) de un texto como "Viola" o "Violin, Viola": el primero de cuerda. */
  function claveInstrumento(texto) {
    for (const parte of String(texto || '').split(',')) {
      const i = UI.iconoInstrumento(parte.trim());
      if (INSTRUMENTOS[i]) return i;
    }
    return null;
  }

  /** En qué instrumento se tocará la escala según a quién se asigna. */
  function instrumentoDestino() {
    const form = document.getElementById('form-tarea');
    if (form.querySelector('input[name="destino"]:checked')?.value === 'elenco') return null;
    const alumno = ALUMNOS.find((a) => Number(a.id) === Number(document.getElementById('tarea-alumno').value));
    return claveInstrumento(alumno?.instrumento);
  }

  function llenarSelectEscalas(nombrePreferido = null) {
    const select = document.getElementById('esc-escala');
    const escalas = escalasDe(instForm);
    const grupos = { facil: 'Fácil', intermedio: 'Intermedio', dificil: 'Difícil' };
    select.innerHTML = escalas.length
      ? Object.entries(grupos).map(([k, t]) => {
        const items = escalas.filter((e) => e.dificultad === k);
        return items.length ? `<optgroup label="${esc(t)}">${items.map((e) => `<option value="${Number(e.id)}">${esc(e.nombre)}</option>`).join('')}</optgroup>` : '';
      }).join('')
      : `<option value="">La biblioteca aún no tiene escalas de ${esc(INSTRUMENTOS[instForm].toLowerCase())}</option>`;
    const igual = nombrePreferido && escalas.find((e) => e.nombre === nombrePreferido);
    if (igual) select.value = String(igual.id);
  }

  /** Cambia el instrumento del formulario (alumno elegido o elenco) conservando la escala por nombre. */
  function sincronizarInstrumento(nombrePreferido = null, octavas = null) {
    const anterior = escalaPorId(document.getElementById('esc-escala').value);
    const inst = instrumentoDestino();
    instForm = inst || 'violin';
    llenarSelectEscalas(typeof nombrePreferido === 'string' ? nombrePreferido : anterior?.nombre);
    const hint = document.getElementById('esc-instrumento');
    if (hint) {
      hint.textContent = inst
        ? `Se toca en ${INSTRUMENTOS[inst].toLowerCase()}: ${CLAVES[inst]}, con el registro y la digitación de ese instrumento.`
        : 'Tarea de elenco: cada estudiante la toca en su instrumento (violín, viola, violonchelo o contrabajo), con su clave y registro. Si su instrumento admite menos octavas, se ajusta solo.';
    }
    elegirEscala(false, octavas || Number(document.querySelector('input[name="esc_octavas"]:checked')?.value || 1));
    const tecAnterior = tecnicaPorId(document.getElementById('tec-ejercicio')?.value);
    llenarTecnica(tecAnterior?.nombre);
    if (document.getElementById('tecnica-config') && !document.getElementById('tecnica-config').classList.contains('hidden')) actualizarTecnica();
  }

  function initEscalas() {
    const form = document.getElementById('form-tarea');
    const select = document.getElementById('esc-escala');
    if (!form || !select) return;

    llenarSelectEscalas();
    document.getElementById('tarea-alumno').addEventListener('change', () => sincronizarInstrumento());
    form.querySelectorAll('input[name="destino"]').forEach((r) => r.addEventListener('change', () => sincronizarInstrumento()));

    document.getElementById('esc-figura').innerHTML = Object.entries(FIGURAS)
      .map(([k, f]) => `<option value="${k}" ${k === 'negra' ? 'selected' : ''}>${esc(f.nombre[0].charAt(0).toUpperCase() + f.nombre[0].slice(1))}</option>`).join('');

    document.getElementById('esc-presets').innerHTML = PRESETS.map(([f, n]) => `
      <button type="button" class="chip-ritmo" data-figura="${f}" data-rep="${n}">${glifo(f)} ${n} ${esc(FIGURAS[f].nombre[n === 1 ? 0 : 1])}</button>`).join('');
    document.getElementById('esc-paleta').innerHTML = Object.entries(FIGURAS).map(([k, f]) => `
      <button type="button" class="chip-ritmo" data-agregar="${k}" title="Agregar ${esc(f.nombre[0])}">${glifo(k)} ${esc(f.nombre[0])}</button>`).join('');
    document.getElementById('esc-celulas-rapidas').innerHTML = CELULAS.map((c, i) => `
      <button type="button" class="chip-ritmo" data-celula="${i}" title="${esc(c.map((f) => FIGURAS[f].nombre[0]).join(' + '))}">${c.map(glifo).join('')} Célula</button>`).join('');

    form.querySelectorAll('input[name="clase_tarea"]').forEach((r) => r.addEventListener('change', alternarClase));
    select.addEventListener('change', () => elegirEscala(true));
    form.querySelectorAll('input[name="esc_ritmo_modo"]').forEach((r) => r.addEventListener('change', alternarRitmo));
    document.getElementById('esc-presets').addEventListener('click', (e) => {
      const b = e.target.closest('[data-figura]');
      if (!b) return;
      document.getElementById('esc-figura').value = b.dataset.figura;
      document.getElementById('esc-repeticiones').value = b.dataset.rep;
      actualizarResumen();
    });
    document.getElementById('esc-paleta').addEventListener('click', (e) => {
      const b = e.target.closest('[data-agregar]');
      if (!b) return;
      const celda = celdas[celdaActiva] || (celdas[celdaActiva] = []);
      if (celda.length >= 16 || pulsosDe([...celda, b.dataset.agregar]) > 16) { toast('Una célula admite hasta 16 figuras y 16 pulsos.'); return; }
      celda.push(b.dataset.agregar);
      pintarCeldas();
    });
    document.getElementById('esc-celulas-rapidas').addEventListener('click', (e) => {
      const b = e.target.closest('[data-celula]');
      if (!b) return;
      celdas[celdaActiva] = [...CELULAS[Number(b.dataset.celula)]];
      pintarCeldas();
    });
    document.getElementById('esc-agregar-celda').addEventListener('click', () => {
      if (celdas.length >= 16) { toast('Puedes usar hasta 16 células.'); return; }
      celdas.push([]);
      celdaActiva = celdas.length - 1;
      pintarCeldas();
    });
    document.getElementById('esc-celdas').addEventListener('click', (e) => {
      const li = e.target.closest('[data-celda]');
      if (!li) return;
      const i = Number(li.dataset.celda);
      if (e.target.closest('[data-quitar]')) {
        celdas[i].pop();
      } else if (e.target.closest('[data-borrar]')) {
        celdas.splice(i, 1);
        if (!celdas.length) celdas = [[]];
        celdaActiva = Math.min(celdaActiva, celdas.length - 1);
      } else {
        celdaActiva = i;
      }
      pintarCeldas();
    });
    ['esc-repeticiones', 'esc-figura', 'esc-bpm', 'esc-compas', 'esc-tolerancia', 'esc-digitacion'].forEach((id) => {
      document.getElementById(id).addEventListener('input', actualizarResumen);
      document.getElementById(id).addEventListener('change', actualizarResumen);
    });
    form.querySelectorAll('input[name="esc_direccion"]').forEach((r) => r.addEventListener('change', actualizarResumen));
    document.getElementById('esc-octavas').addEventListener('change', () => { actualizarDigitacion(); actualizarResumen(); });
    document.getElementById('esc-probar').addEventListener('click', () => {
      const config = configEscala();
      if (!config) return;
      const id = Number(select.value);
      window.open(`/biblioteca/escalas/${id}/probar?config=${encodeURIComponent(JSON.stringify(config))}`, '_blank', 'noopener');
    });
  }

  function alternarClase() {
    const form = document.getElementById('form-tarea');
    const clase = form.querySelector('input[name="clase_tarea"]:checked').value;
    const escala = clase === 'escala';
    const tecnica = clase === 'tecnica';
    document.getElementById('escala-config').classList.toggle('hidden', !escala);
    document.getElementById('tecnica-config')?.classList.toggle('hidden', !tecnica);
    document.getElementById('campo-tipo').classList.toggle('hidden', escala || tecnica);
    document.getElementById('titulo-hint').textContent = (escala || tecnica) ? 'Opcional: si lo dejas vacío se usa el nombre del ejercicio.' : '';
    form.titulo.placeholder = escala ? 'Escala de La mayor - 1 octava' : tecnica ? 'Trino 1-2 · 2 alto - velocidad' : 'Ej: Estudio n.º 3 de Wohlfahrt';
    if (escala && !escalasDe(instForm).length) toast('La biblioteca aún no tiene escalas para este instrumento. El director puede agregarlas.');
    if (escala) actualizarResumen();
    if (tecnica) actualizarTecnica();
  }

  /* ----- ejercicios técnicos y métodos digitalizados ----- */

  function llenarTecnica(nombrePreferido = null) {
    const select = document.getElementById('tec-ejercicio');
    if (!select) return;
    const items = tecnicaDe(instForm);
    const grupos = [
      ['tecnica_mano_izquierda', 'facil', 'Técnica · Fácil'], ['tecnica_mano_izquierda', 'intermedio', 'Técnica · Intermedio'], ['tecnica_mano_izquierda', 'dificil', 'Técnica · Difícil'],
      ['metodo', null, 'Métodos digitalizados'],
    ];
    select.innerHTML = items.length ? grupos.map(([cat, dif, titulo]) => {
      const g = items.filter((e) => e.categoria === cat && (!dif || e.dificultad === dif));
      return g.length ? `<optgroup label="${esc(titulo)}">${g.map((e) => `<option value="${Number(e.id)}">${esc(e.nombre)}</option>`).join('')}</optgroup>` : '';
    }).join('') : `<option value="">No hay ejercicios técnicos para ${esc(INSTRUMENTOS[instForm].toLowerCase())}</option>`;
    const igual = nombrePreferido && items.find((e) => e.nombre === nombrePreferido);
    if (igual) select.value = String(igual.id);
  }

  function configTecnica() {
    const id = Number(document.getElementById('tec-ejercicio')?.value);
    if (!id) { toast('Elige un ejercicio de la biblioteca.'); return null; }
    const bpm = Number(document.getElementById('tec-bpm').value) || 60;
    return {
      modo: document.querySelector('input[name="tec_modo"]:checked').value,
      bpm,
      bpm_objetivo: Math.max(bpm, Number(document.getElementById('tec-objetivo').value) || bpm),
      paso: Number(document.getElementById('tec-paso').value) || 4,
      figura: document.getElementById('tec-figura').value || null,
      compas: document.getElementById('tec-compas').value,
      tolerancia: Number(document.getElementById('tec-tolerancia').value) || 10,
      digitacion: true,
    };
  }

  function actualizarTecnica() {
    const e = tecnicaPorId(document.getElementById('tec-ejercicio')?.value);
    const velocidad = document.querySelector('input[name="tec_modo"]:checked')?.value === 'velocidad';
    document.getElementById('tec-objetivo-campo').classList.toggle('hidden', !velocidad);
    document.getElementById('tec-paso-campo').classList.toggle('hidden', !velocidad);
    document.getElementById('tec-figura-campo').classList.toggle('hidden', !e || e.categoria === 'metodo');
    const inst = instrumentoDestino();
    document.getElementById('tec-instrumento').textContent = inst
      ? `Se toca en ${INSTRUMENTOS[inst].toLowerCase()} (${CLAVES[inst]}).`
      : 'Tarea de elenco: la técnica se toca en el instrumento de cada estudiante; los métodos, tal como están escritos.';
    if (!e) { document.getElementById('tec-info').textContent = ''; document.getElementById('tec-resumen').textContent = 'Elige un ejercicio.'; return; }
    const fuente = e.fuente && e.fuente.pagina ? ` · ${e.fuente.metodo || e.fuente.titulo}, pág. ${e.fuente.pagina}` : '';
    document.getElementById('tec-info').textContent = `${e.categoria_texto} · ${e.cantidad_notas} notas: ${e.primeras_notas.join(' ')}…${fuente}${e.descripcion ? ` · ${e.descripcion}` : ''}`;
    const c = configTecnica() || {};
    document.getElementById('tec-resumen').textContent = `${e.nombre} · ${c.modo === 'velocidad' ? `velocidad de ${c.bpm} a ${c.bpm_objetivo}, sube ${c.paso}` : `afinación nota por nota, negra = ${c.bpm}`} · compás ${c.compas} · ±${c.tolerancia} cents`;
    if (e.compas && !document.getElementById('tec-compas').dataset.tocado) document.getElementById('tec-compas').value = e.compas;
  }

  function initTecnica() {
    const select = document.getElementById('tec-ejercicio');
    if (!select) return;
    llenarTecnica();
    select.addEventListener('change', () => {
      const e = tecnicaPorId(select.value);
      if (e) document.getElementById('tec-tolerancia').value = String(TOLERANCIA[e.dificultad] || 10);
      actualizarTecnica();
    });
    document.querySelectorAll('input[name="tec_modo"]').forEach((r) => r.addEventListener('change', actualizarTecnica));
    ['tec-bpm', 'tec-objetivo', 'tec-paso', 'tec-figura', 'tec-tolerancia'].forEach((id) => document.getElementById(id).addEventListener('input', actualizarTecnica));
    document.getElementById('tec-compas').addEventListener('change', (ev) => { ev.target.dataset.tocado = '1'; actualizarTecnica(); });
    document.getElementById('tec-probar').addEventListener('click', () => {
      const config = configTecnica();
      if (!config) return;
      window.open(`/biblioteca/ejercicios/${Number(select.value)}/probar?config=${encodeURIComponent(JSON.stringify(config))}`, '_blank', 'noopener');
    });
  }

  function alternarRitmo() {
    const patron = document.querySelector('input[name="esc_ritmo_modo"]:checked').value === 'patron';
    document.getElementById('esc-uniforme').classList.toggle('hidden', patron);
    document.getElementById('esc-presets').classList.toggle('hidden', patron);
    document.getElementById('esc-patron').classList.toggle('hidden', !patron);
    if (patron) pintarCeldas(); else actualizarResumen();
  }

  function elegirEscala(cambiarTolerancia, octavasPrevias = null) {
    const e = escalaPorId(document.getElementById('esc-escala').value);
    const octavas = document.getElementById('esc-octavas');
    if (!e) { octavas.innerHTML = ''; document.getElementById('esc-info').textContent = ''; actualizarResumen(); return; }
    const elegida = Math.min(octavasPrevias || 1, e.octavas_max);
    octavas.innerHTML = Array.from({ length: e.octavas_max }, (_, i) => i + 1).map((n) => `
      <label class="inline"><input type="radio" name="esc_octavas" value="${n}" ${n === elegida ? 'checked' : ''} /> ${n} ${n === 1 ? 'octava' : 'octavas'}</label>`).join('');
    document.getElementById('esc-info').textContent = `${e.notas_una_octava.join(' ')} · ${e.armadura.texto}${e.descripcion ? ` · ${e.descripcion}` : ''}`;
    if (cambiarTolerancia) document.getElementById('esc-tolerancia').value = String(TOLERANCIA[e.dificultad] || 10);
    actualizarDigitacion();
    actualizarResumen();
  }

  function actualizarDigitacion() {
    const e = escalaPorId(document.getElementById('esc-escala').value);
    const oct = document.querySelector('input[name="esc_octavas"]:checked')?.value;
    const tiene = Boolean(e && oct && (e.tiene_digitacion || []).includes(String(oct)));
    document.getElementById('esc-digitacion-campo').classList.toggle('hidden', !tiene);
  }

  function pintarCeldas() {
    const ol = document.getElementById('esc-celdas');
    ol.innerHTML = celdas.map((c, i) => `
      <li class="celda ${i === celdaActiva ? 'activa' : ''}" data-celda="${i}" tabindex="0" aria-label="Célula ${i + 1}">
        <span class="celda-num">Célula ${i + 1}</span>
        <span class="celda-figuras">${c.length ? c.map(glifo).join('') : '<span class="celda-vacia">Elige figuras abajo</span>'}</span>
        <span class="celda-pulsos">${textoPulsos(pulsosDe(c))}</span>
        <button type="button" data-quitar aria-label="Quitar la última figura">${icon('devolver')}</button>
        <button type="button" data-borrar aria-label="Eliminar la célula">${icon('borrar')}</button>
      </li>`).join('');
    actualizarResumen();
  }

  function ritmoActual() {
    const patron = document.querySelector('input[name="esc_ritmo_modo"]:checked')?.value === 'patron';
    if (patron) {
      const llenas = celdas.filter((c) => c.length);
      return llenas.length ? { modo: 'patron', celdas: llenas } : null;
    }
    return {
      modo: 'uniforme',
      figura: document.getElementById('esc-figura').value,
      repeticiones: Number(document.getElementById('esc-repeticiones').value) || 1,
    };
  }

  function configEscala() {
    const id = Number(document.getElementById('esc-escala').value);
    if (!id) { toast('Elige una escala de la biblioteca.'); return null; }
    const ritmo = ritmoActual();
    if (!ritmo) { toast('Agrega al menos una figura al ritmo propio.'); return null; }
    return {
      octavas: Number(document.querySelector('input[name="esc_octavas"]:checked')?.value || 1),
      direccion: document.querySelector('input[name="esc_direccion"]:checked').value,
      ritmo,
      bpm: Number(document.getElementById('esc-bpm').value) || 60,
      compas: document.getElementById('esc-compas').value,
      tolerancia: Number(document.getElementById('esc-tolerancia').value) || 10,
      digitacion: document.getElementById('esc-digitacion').checked,
    };
  }

  function textoRitmo(r) {
    if (!r) return 'sin ritmo';
    if (r.modo === 'patron') {
      const t = r.celdas.map((c) => c.map((f) => FIGURAS[f].nombre[0]).join(' + '));
      return t.length === 1 ? `ritmo propio: ${t[0]}` : `ritmo propio de ${t.length} células`;
    }
    const n = r.repeticiones;
    return `${n === 1 ? 'una' : n} ${FIGURAS[r.figura].nombre[n === 1 ? 0 : 1]} por nota`;
  }

  function actualizarResumen() {
    const e = escalaPorId(document.getElementById('esc-escala')?.value);
    const p = document.getElementById('esc-resumen');
    if (!p) return;
    if (!e) { p.textContent = 'Elige una escala.'; return; }
    const oct = Number(document.querySelector('input[name="esc_octavas"]:checked')?.value || 1);
    const dir = document.querySelector('input[name="esc_direccion"]:checked')?.value === 'subir' ? 'solo sube' : 'sube y baja';
    const compas = document.getElementById('esc-compas').value;
    const unidad = compas === '2/2' ? 'blanca' : compas === '6/8' ? 'corchea' : 'negra';
    const notas = (dir === 'solo sube' ? 7 * oct + 1 : 14 * oct + 1);
    p.textContent = `Escala de ${e.nombre} · ${oct} ${oct === 1 ? 'octava' : 'octavas'} · ${dir} (${notas} notas) · ${textoRitmo(ritmoActual())} · ${unidad} = ${document.getElementById('esc-bpm').value} · compás ${compas} · ±${document.getElementById('esc-tolerancia').value} cents`;
    document.querySelectorAll('#esc-presets [data-figura]').forEach((b) => b.classList.toggle('activo',
      b.dataset.figura === document.getElementById('esc-figura').value && b.dataset.rep === String(document.getElementById('esc-repeticiones').value)));
  }

  /** Deja el formulario listo: tarea libre, o escala elegida (con su configuración al editar). */
  function prepararEscala(escalaId, config) {
    const form = document.getElementById('form-tarea');
    if (!document.getElementById('esc-escala')) return;
    const tec = escalaId ? tecnicaPorId(escalaId) : null;
    if (tec) {
      form.querySelector('input[name="clase_tarea"][value="tecnica"]').checked = true;
      sincronizarInstrumento();
      llenarTecnica(tec.nombre);
      if (!document.getElementById('tec-ejercicio').value || !tecnicaPorId(document.getElementById('tec-ejercicio').value)) llenarTecnica();
      const c = config || {};
      form.querySelector(`input[name="tec_modo"][value="${c.modo === 'afinacion' ? 'afinacion' : 'velocidad'}"]`).checked = true;
      document.getElementById('tec-bpm').value = c.bpm || 60;
      document.getElementById('tec-objetivo').value = c.bpm_objetivo || 100;
      document.getElementById('tec-paso').value = c.paso || 4;
      document.getElementById('tec-figura').value = c.figura || '';
      document.getElementById('tec-compas').value = c.compas || tec.compas || '4/4';
      delete document.getElementById('tec-compas').dataset.tocado;
      document.getElementById('tec-tolerancia').value = String(c.tolerancia || TOLERANCIA[tec.dificultad] || 10);
      alternarClase();
      return;
    }
    const esEscala = Boolean(escalaId);
    form.querySelector(`input[name="clase_tarea"][value="${esEscala ? 'escala' : 'libre'}"]`).checked = true;
    celdas = [['negra']];
    celdaActiva = 0;
    // La escala se busca por nombre en el instrumento del alumno elegido.
    sincronizarInstrumento(esEscala ? (escalaPorId(escalaId)?.nombre || null) : null, config ? config.octavas : 1);
    if (!config) elegirEscala(true, 1);
    if (config) {
      form.querySelector(`input[name="esc_direccion"][value="${config.direccion === 'subir' ? 'subir' : 'subir_bajar'}"]`).checked = true;
      const patron = config.ritmo && config.ritmo.modo === 'patron';
      form.querySelector(`input[name="esc_ritmo_modo"][value="${patron ? 'patron' : 'uniforme'}"]`).checked = true;
      if (patron) celdas = config.ritmo.celdas.map((c) => [...c]);
      else if (config.ritmo) {
        document.getElementById('esc-figura').value = config.ritmo.figura;
        document.getElementById('esc-repeticiones').value = config.ritmo.repeticiones;
      }
      document.getElementById('esc-bpm').value = config.bpm;
      document.getElementById('esc-compas').value = config.compas;
      document.getElementById('esc-tolerancia').value = String(config.tolerancia);
      document.getElementById('esc-digitacion').checked = config.digitacion !== false;
    } else {
      form.querySelector('input[name="esc_direccion"][value="subir_bajar"]').checked = true;
      form.querySelector('input[name="esc_ritmo_modo"][value="uniforme"]').checked = true;
      document.getElementById('esc-figura').value = 'negra';
      document.getElementById('esc-repeticiones').value = 4;
      document.getElementById('esc-bpm').value = 60;
      document.getElementById('esc-compas').value = '4/4';
      document.getElementById('esc-digitacion').checked = true;
    }
    actualizarDigitacion();
    alternarRitmo();
    alternarClase();
  }

  function renderBiblioteca() {
    const grid = document.getElementById('biblioteca-grid');
    if (!grid) return;
    if (catBiblioteca !== 'escala') { renderBibliotecaTecnica(grid); return; }
    const escalas = escalasDe(instBiblioteca);
    const items = escalas.filter((e) => filtroBiblioteca === 'todas' || e.dificultad === filtroBiblioteca);
    grid.innerHTML = items.length ? items.map((e) => `
      <article class="card escala-tarjeta">
        <h3>${esc(e.titulo)}</h3>
        <p class="notas">${esc(e.notas_una_octava.join(' '))}</p>
        <div class="chips">
          <span class="dif-${esc(e.dificultad)}">${esc(e.dificultad_texto)}</span>
          <span>${esc(e.armadura.texto)}</span>
          <span>Hasta ${Number(e.octavas_max)} ${Number(e.octavas_max) === 1 ? 'octava' : 'octavas'}</span>
        </div>
        ${e.descripcion ? `<p class="hint">${esc(e.descripcion)}</p>` : ''}
        <div class="acciones">
          <button class="btn-msea small" type="button" data-asignar="${Number(e.id)}">${icon('tareas')} Asignar</button>
          <a class="btn-msea small secondary" href="/biblioteca/escalas/${Number(e.id)}/probar" target="_blank" rel="noopener">${icon('ver')} Probar</a>
        </div>
      </article>`).join('') : vacio(escalas.length ? 'No hay escalas con esta dificultad.' : `La biblioteca aún no tiene escalas de ${INSTRUMENTOS[instBiblioteca].toLowerCase()}.`, 'El director agrega las escalas desde su panel.', 'afinacion');
    grid.querySelectorAll('[data-asignar]').forEach((b) => b.addEventListener('click', () => abrirFormTarea(null, { escalaId: Number(b.dataset.asignar) })));
  }


  function renderBibliotecaTecnica(grid) {
    const todos = tecnicaDe(instBiblioteca).filter((e) => e.categoria === catBiblioteca);
    const items = todos.filter((e) => filtroBiblioteca === 'todas' || e.dificultad === filtroBiblioteca);
    const vacioTexto = catBiblioteca === 'metodo'
      ? [`Todavía no hay métodos digitalizados para ${INSTRUMENTOS[instBiblioteca].toLowerCase()}.`, 'El director los sube desde el digitalizador de métodos.']
      : ['No hay ejercicios con esta dificultad.', ''];
    grid.innerHTML = items.length ? items.map((e) => `
      <article class="card escala-tarjeta">
        <h3>${esc(e.nombre)}</h3>
        <p class="notas">${esc(e.primeras_notas.join(' '))}${e.cantidad_notas > e.primeras_notas.length ? ' …' : ''}</p>
        <div class="chips">
          <span class="dif-${esc(e.dificultad)}">${esc(e.dificultad_texto)}</span>
          <span>${Number(e.cantidad_notas)} notas</span>
          ${e.fuente && e.fuente.pagina ? `<span>${esc(e.fuente.metodo || e.fuente.titulo)}, pág. ${Number(e.fuente.pagina)}</span>` : ''}
        </div>
        ${e.descripcion ? `<p class="hint">${esc(e.descripcion)}</p>` : ''}
        <div class="acciones">
          <button class="btn-msea small" type="button" data-asignar-tecnica="${Number(e.id)}">${icon('tareas')} Asignar</button>
          <a class="btn-msea small secondary" href="/biblioteca/ejercicios/${Number(e.id)}/probar" target="_blank" rel="noopener">${icon('ver')} Probar</a>
        </div>
      </article>`).join('') : vacio(todos.length ? vacioTexto[0].replace(/^.*$/, 'No hay ejercicios con esta dificultad.') : vacioTexto[0], vacioTexto[1], catBiblioteca === 'metodo' ? 'archivo' : 'tecnica');
    grid.querySelectorAll('[data-asignar-tecnica]').forEach((b) => b.addEventListener('click', () => abrirFormTarea(null, { tecnicaId: Number(b.dataset.asignarTecnica) })));
  }

  function initBiblioteca() {
    document.querySelectorAll('#biblioteca-categorias [data-categoria]').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('#biblioteca-categorias [data-categoria]').forEach((x) => x.classList.toggle('active', x === b));
      catBiblioteca = b.dataset.categoria;
      renderBiblioteca();
    }));
    document.querySelectorAll('#biblioteca-instrumentos [data-instrumento]').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('#biblioteca-instrumentos [data-instrumento]').forEach((x) => x.classList.toggle('active', x === b));
      instBiblioteca = b.dataset.instrumento;
      renderBiblioteca();
    }));
    // Se abre en el instrumento más común entre los alumnos del profesor.
    const conteo = {};
    ALUMNOS.forEach((a) => { const i = claveInstrumento(a.instrumento); if (i) conteo[i] = (conteo[i] || 0) + 1; });
    const comun = Object.entries(conteo).sort((x, y) => y[1] - x[1])[0]?.[0];
    if (comun) document.querySelector(`#biblioteca-instrumentos [data-instrumento="${comun}"]`)?.click();
    document.querySelectorAll('#biblioteca-filtros [data-dificultad]').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('#biblioteca-filtros [data-dificultad]').forEach((x) => x.classList.toggle('active', x === b));
      filtroBiblioteca = b.dataset.dificultad;
      renderBiblioteca();
    }));
  }

  /* ---------------------------------------------------------- entregas: escuchar, calificar, devolver */

  async function abrirEntregas(idTarea) {
    const tarea = TAREAS.find((t) => t.id === idTarea);
    setText('entregas-titulo', `Entregas: ${tarea ? tarea.titulo : ''}`);
    const ul = document.getElementById('entregas-detalle');
    ul.innerHTML = `<li>${vacio('Cargando entregas...', '', 'reloj')}</li>`;
    abrirModal('modal-entregas');
    try {
      const r = await api(`/dashboard-profesor/tareas/${idTarea}/entregas`);
      pintarEntregas(r.entregas, tarea);
    } catch (err) {
      ul.innerHTML = `<li>${vacio('No se pudieron cargar las entregas.', err.message, 'alerta')}</li>`;
    }
  }

  function pintarEntregas(entregas, tarea) {
    const ul = document.getElementById('entregas-detalle');
    if (!entregas.length) {
      ul.innerHTML = `<li>${vacio('Todavía no hay entregas para esta tarea.', '', 'subir')}</li>`;
      return;
    }
    const estados = { entregada: 'Por revisar', devuelta: 'Devuelta para corregir', calificada: 'Calificada' };
    ul.innerHTML = entregas.map((e) => `
      <li class="entrega-detalle" data-id="${Number(e.id_entrega)}">
        <div class="ed-cabecera"><strong>${esc(e.alumno)}</strong><span class="estado-tag ${esc(e.estado)}">${esc(estados[e.estado] || e.estado)}</span><span class="hint">${esc(UI.fecha(e.entregado_at))}</span></div>
        ${e.comentario_estudiante ? `<blockquote class="ed-comentario">${esc(e.comentario_estudiante)}</blockquote>` : ''}
        ${e.resultado_escala ? resultadoEscalaHtml(e.resultado_escala) : ''}
        ${e.archivo_url ? `<audio controls preload="none" src="${esc(UI.safeUrl(e.archivo_url))}"></audio><a class="tcf-link" href="${esc(UI.safeUrl(e.archivo_url))}?descargar=1">${icon('descargar')} Descargar ${esc(e.archivo_nombre)}</a>` : (e.resultado_escala ? '' : '<p class="hint">Sin grabación adjunta.</p>')}
        <form class="msea-form ed-form" novalidate>
          <div class="field-row-2">
            <label>Calificación (0-100) <input type="number" name="calificacion" min="0" max="100" step="1" value="${e.calificacion !== null ? Number(e.calificacion) : ''}" /></label>
            <label>Observación <textarea name="comentario_profesor" maxlength="1000">${esc(e.comentario_profesor || '')}</textarea></label>
          </div>
          <div class="ed-acciones">
            <button class="btn-msea small" type="submit" data-accion="calificar" ${e.estado === 'devuelta' ? 'disabled' : ''}>${icon('check')} ${e.estado === 'calificada' ? 'Corregir nota' : 'Calificar'}</button>
            <button class="btn-msea small secondary" type="submit" data-accion="devolver" ${e.estado !== 'entregada' ? 'disabled' : ''}>${icon('devolver')} Devolver para corregir</button>
          </div>
        </form>
      </li>`).join('');

    ul.querySelectorAll('.ed-form').forEach((form) => {
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const accion = ev.submitter?.dataset.accion || 'calificar';
        const id = Number(form.closest('[data-id]').dataset.id);
        const datos = Object.fromEntries(new FormData(form));
        try {
          if (accion === 'devolver') {
            await conCarga(ev.submitter, api(`/dashboard-profesor/entregas/${id}/devolver`, { json: { comentario_profesor: datos.comentario_profesor } }));
            toast('Entrega devuelta. El alumno recibió tu observación.');
          } else {
            const r = await conCarga(ev.submitter, api(`/dashboard-profesor/entregas/${id}/calificar`, { json: datos }));
            toast(r.xp_otorgado ? `${r.message}. El alumno ganó ${r.xp_otorgado} XP.` : r.message);
          }
          const r2 = await api(`/dashboard-profesor/tareas/${tarea.id}/entregas`);
          pintarEntregas(r2.entregas, tarea);
          tarea.entregas_por_revisar = r2.entregas.filter((x) => x.estado === 'entregada').length;
          tarea.entregas_calificadas = r2.entregas.filter((x) => x.estado === 'calificada').length;
          renderResumen();
          renderEntregasPendientes();
          renderTareas();
        } catch (err) {
          UI.mostrarErrores(form, err.errors);
          toast(err.message);
        }
      });
    });
  }

  /** Resultado de una escala tocada con el micrófono: resumen y un punto por nota. */
  function resultadoEscalaHtml(r) {
    const notas = Array.isArray(r.notas) ? r.notas : [];
    const seg = Number(r.duracion_s) || 0;
    const tiempo = seg >= 60 ? `${Math.floor(seg / 60)} min ${seg % 60} s` : `${seg} s`;
    const puntos = notas.map((n, i) => {
      const c = Math.abs(Number(n.cents) || 0);
      const clase = c <= 5 ? 'p-bien' : c <= 10 ? 'p-medio' : 'p-flojo';
      return `<span class="${clase}" title="Nota ${i + 1}: ${Number(n.cents) > 0 ? '+' : ''}${Number(n.cents).toFixed(1)} cents, ${(Number(n.ms) / 1000).toFixed(1)} s para afinar"></span>`;
    }).join('');
    return `<div class="resultado-escala">
      <p><strong>${icon('afinacion')} Escala ${r.completada ? 'completa' : 'incompleta'}</strong> · ${Number(r.notas_afinadas)} de ${Number(r.notas_total)} notas · desvío medio ${r.desvio_medio_cents !== null ? Number(r.desvio_medio_cents).toFixed(1) : '-'} cents · ${esc(tiempo)}</p>
      <div class="puntos-escala" aria-label="Afinación por nota">${puntos}</div>
      <p class="hint">Medido con el micrófono en el navegador del alumno (no verificado). Verde: ±5 cents · claro: ±10 · amarillo: más de 10.</p>
    </div>`;
  }

  /* ---------------------------------------------------------- ejercicios y progreso grupal */

  function renderEjercicios() {
    const grid = document.getElementById('ejercicios-grid');
    if (!grid) return;
    grid.innerHTML = EJERCICIOS.length ? EJERCICIOS.map((e) => `
      <article class="card ejercicio-card">
        <div class="ej-icon">${icon(UI.iconoTipo(String(e.tipo).toLowerCase()) === 'campana' ? 'musica' : UI.iconoTipo(String(e.tipo).toLowerCase()), 'icon-lg icon-green')}</div>
        <p class="ej-nombre">${esc(e.nombre)}</p>
        <p class="ej-desc">${esc(e.desc)}</p>
        <div class="ej-chips"><span class="ej-chip">${esc(e.tipo)}</span><span class="ej-chip">${esc(e.dificultad)}</span><span class="ej-chip">${Number(e.xp)} XP</span></div>
      </article>`).join('') : vacio('Tu biblioteca está vacía.', 'Cada tarea que crees agrega su ejercicio aquí.', 'ejercicios');
  }

  function renderProgresoGrupal() {
    const grid = document.getElementById('progreso-full-grid');
    if (!grid) return;
    if (!ALUMNOS.length) {
      grid.innerHTML = vacio('Sin alumnos para mostrar progreso.', '', 'progreso');
      return;
    }
    const grupos = {};
    ALUMNOS.forEach((a) => {
      String(a.instrumento || 'Sin instrumento').split(',').map((s) => s.trim()).forEach((inst) => {
        grupos[inst] ||= { total: 0, n: 0 };
        grupos[inst].total += Number(a.puntos);
        grupos[inst].n += 1;
      });
    });
    const max = Math.max(1, ...Object.values(grupos).map((g) => g.total / g.n));
    grid.innerHTML = Object.entries(grupos).map(([inst, g]) => {
      const prom = Math.round(g.total / g.n);
      return `
        <article class="card progreso-item">
          <div class="pi-row-header"><span class="pi-inst-name">${icon(UI.iconoInstrumento(inst), 'icon-green')} ${esc(inst)}</span><span class="pi-pct">${prom} XP promedio</span></div>
          <div class="pi-track"><div class="pi-fill" data-ancho="${Math.round((prom / max) * 100)}"></div></div>
          <p class="hint">${g.n} alumno(s)</p>
        </article>`;
    }).join('');
  }

  /* ---------------------------------------------------------- perfil */

  function initPerfil() {
    const overlay = document.getElementById('perfil-overlay');
    const drawer = document.getElementById('perfil-drawer');
    let origen = null;
    const abrir = (e) => {
      origen = e?.currentTarget || null;
      drawer?.classList.add('open'); overlay?.classList.add('active');
      drawer?.querySelector('input, button')?.focus();
    };
    const cerrar = () => {
      if (!drawer?.classList.contains('open')) return;
      drawer.classList.remove('open'); overlay?.classList.remove('active');
      origen?.focus();
    };
    document.addEventListener('keydown', (e) => {
      if (!drawer?.classList.contains('open')) return;
      if (e.key === 'Escape') cerrar(); else UI.atraparFoco(drawer, e);
    });
    document.getElementById('btn-open-perfil')?.addEventListener('click', abrir);
    document.getElementById('header-avatar-btn')?.addEventListener('click', abrir);
    document.getElementById('perfil-close')?.addEventListener('click', cerrar);
    overlay?.addEventListener('click', cerrar);

    const form = document.getElementById('form-perfil');
    form.nombres.value = PROFESOR.nombres || '';
    form.apellido_paterno.value = PROFESOR.apellidoPaterno || '';
    form.apellido_materno.value = PROFESOR.apellidoMaterno || '';
    form.correo.value = PROFESOR.email || '';
    form.celular.value = PROFESOR.celular || '';
    form.correo.addEventListener('input', () => {
      document.getElementById('campo-contrasena-actual').classList.toggle('hidden', form.correo.value.trim().toLowerCase() === String(PROFESOR.email).toLowerCase());
    });

    const enviar = async (extra = {}, boton = form.querySelector('button[type="submit"]')) => {
      const datos = Object.fromEntries(new FormData(form));
      if (!datos.contrasena_actual) delete datos.contrasena_actual;
      const r = await conCarga(boton, api('/dashboard-profesor/perfil', { json: { ...datos, ...extra } }));
      Object.assign(PROFESOR, { nombres: datos.nombres, email: datos.correo, nombre: `${datos.nombres} ${datos.apellido_paterno || ''}`.trim() });
      if (r.foto) PROFESOR.foto = r.foto;
      aplicarProfesor();
      return r;
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await enviar();
        UI.mostrarErrores(form, {});
        toast('Perfil actualizado.');
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      }
    });

    document.getElementById('avatar-input')?.addEventListener('change', async (e) => {
      const archivo = e.target.files[0];
      if (!archivo) return;
      if (!/^image\/(png|jpe?g|webp)$/.test(archivo.type)) { toast('Usa una imagen PNG, JPG o WEBP.'); return; }
      const dataUrl = await new Promise((res) => { const fr = new FileReader(); fr.onload = (ev) => res(ev.target.result); fr.readAsDataURL(archivo); });
      const img = await new Promise((res) => { const i = new Image(); i.onload = () => res(i); i.src = dataUrl; });
      const escala = Math.min(1, 512 / Math.max(img.width, img.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(img.width * escala);
      canvas.height = Math.round(img.height * escala);
      canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
      try {
        await enviar({ foto: canvas.toDataURL('image/jpeg', 0.82) }, null);
        toast('Foto actualizada.');
      } catch (err) {
        toast(err.message);
      }
    });

    document.getElementById('form-contrasena')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.currentTarget;
      try {
        await conCarga(f.querySelector('button[type="submit"]'), api('/dashboard-profesor/contrasena', { json: Object.fromEntries(new FormData(f)) }));
        f.reset();
        UI.mostrarErrores(f, {});
        toast('Contraseña actualizada.');
      } catch (err) {
        UI.mostrarErrores(f, err.errors);
        toast(err.message);
      }
    });
  }

  /* ---------------------------------------------------------- mensajes */

  async function cargarContactos() {
    if (CONTACTOS.length) return;
    try {
      const r = await api('/mensajes');
      CONTACTOS = lista(r.contactos);
      MENSAJES = lista(r.mensajes);
      renderMensajes();
      api('/mensajes/leidos', { json: {} }).catch(() => {});
    } catch (err) {
      toast(err.message);
    }
  }

  function renderMensajes() {
    const select = document.getElementById('mensaje-destino');
    if (select) {
      select.innerHTML = CONTACTOS.length
        ? CONTACTOS.map((c) => `<option value="${Number(c.id)}">${esc(c.nombre)} (${esc(c.rol)})</option>`).join('')
        : '<option value="">Cargando contactos...</option>';
    }
    const ul = document.getElementById('mensajes-lista');
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
        const c = CONTACTOS.find((x) => String(x.id) === String(datos.id_destinatario));
        MENSAJES.unshift({ enviado: true, con: c?.nombre || '', asunto: datos.asunto, contenido: datos.contenido, fecha: new Date().toISOString(), leido: true });
        form.reset();
        renderMensajes();
        toast('Mensaje enviado.');
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      }
    });
  }
})();
