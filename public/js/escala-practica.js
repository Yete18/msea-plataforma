/* ============================================================
   MSEA - Práctica guiada de escalas
   - Nota objetivo con su frecuencia exacta (La4 = 440 Hz, temperamento igual).
   - La siguiente nota aparece solo cuando la actual se sostiene afinada
     (dentro de la tolerancia que indicó el profesor) durante medio segundo.
   - Pentagrama con clave de Sol y armadura; las notas aparecen al tocarlas.
   - Ritmo de cada nota y metrónomo con compás y acento.
   La detección de altura se hace en el navegador (algoritmo YIN): es una
   ayuda para practicar, no una evaluación verificada.
   ============================================================ */
(function () {
  'use strict';

  const UI = window.MSEA_UI;
  const { esc, api, conCarga, toast } = UI;
  const E = window.MSEA_ESCALA;
  const MODO = window.MSEA_ESCALA_MODO;
  const URL_ENTREGA = window.MSEA_ESCALA_ENTREGAR;
  if (!E || !Array.isArray(E.notas) || !E.notas.length) return;

  const NOTAS = E.notas;
  const RITMO = E.ritmo || [];
  const TOL = Number(E.config.tolerancia) || 10;
  const SOSTENER_MS = 500;   // tiempo afinado que hace falta para avanzar (modo afinación)
  // Modo velocidad (ejercicios técnicos): se toca al tempo del metrónomo. Cada nota debe sonar
  // afinada, pero basta con una fracción de su duración; el tempo sube de a poco hasta la meta.
  const VEL = E.velocidad || null;
  const ES_VELOCIDAD = Boolean(VEL && VEL.modo === 'velocidad');
  const ES_TECNICA = E.tipo === 'tecnica';
  const GRACIA_MS = 160;     // pequeñas caídas (arco, cambio de cuerda) no reinician el conteo
  const $ = (id) => document.getElementById(id);

  /* ---------------------------------------------------------- textos de figuras */

  const FIGURA = {
    redonda: ['redonda', 'redondas'],
    blanca_puntillo: ['blanca con puntillo', 'blancas con puntillo'],
    blanca: ['blanca', 'blancas'],
    negra_puntillo: ['negra con puntillo', 'negras con puntillo'],
    negra: ['negra', 'negras'],
    corchea_puntillo: ['corchea con puntillo', 'corcheas con puntillo'],
    corchea: ['corchea', 'corcheas'],
    semicorchea: ['semicorchea', 'semicorcheas'],
  };

  function textoCelda(figuras) {
    if (figuras.every((f) => f === figuras[0])) {
      const n = figuras.length;
      return `${n === 1 ? 'Una' : n} ${FIGURA[figuras[0]][n === 1 ? 0 : 1]}`;
    }
    const t = figuras.map((f) => FIGURA[f][0]).join(' + ');
    return t.charAt(0).toUpperCase() + t.slice(1);
  }

  /* ---------------------------------------------------------- SMuFL (fuente Bravura) */

  const G = {
    claveSol: '',
    cabezaNegra: '',
    cabezaBlanca: '',
    cabezaRedonda: '',
    corchete8: '',
    corchete16: '',
    puntillo: '',
    sostenido: '',
    bemol: '',
    becuadro: '',
    dobleSostenido: '',
    dobleBemol: '',
  };
  const ALTERACION = { 1: G.sostenido, '-1': G.bemol, 0: G.becuadro, 2: G.dobleSostenido, '-2': G.dobleBemol };
  const LETRAS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
  const ns = 'http://www.w3.org/2000/svg';

  /* ---------------------------------------------------------- pentagrama */

  const SP = 12;                 // espacio entre líneas (px)
  const PASO_NOTA = 46;          // separación horizontal entre notas
  // Claves: Sol (violín), Do en tercera línea (viola) y Fa en cuarta línea (violonchelo y contrabajo).
  //  base: nota de la primera línea; linea: paso donde se apoya el signo de la clave;
  //  bajar: octavas que se restan a la armadura de la clave de Sol para dibujarla en esta clave.
  const CLAVES = {
    sol: { glifo: '\uE050', base: ['E', 4], linea: 2, bajar: 0 },
    do: { glifo: '\uE05C', base: ['F', 3], linea: 4, bajar: 1 },
    fa: { glifo: '\uE062', base: ['G', 2], linea: 6, bajar: 2 },
  };
  const INST = E.instrumento || { clef: 'sol', clave: 'violin', nombre: 'Violín' };
  const CLAVE = CLAVES[INST.clef] || CLAVES.sol;
  // Octavas de cada alteración de la armadura en clave de Sol (orden de los sostenidos y bemoles).
  const ARM_OCTAVA = {
    sostenidos: { F: 5, C: 5, G: 5, D: 5, A: 4, E: 5, B: 4 },
    bemoles: { B: 4, E: 5, A: 4, D: 5, G: 4, C: 5, F: 4 },
  };
  const armadura = E.escala.armadura || { tipo: null, letras: [] };
  const alterClave = {};
  (armadura.letras || []).forEach((l) => { alterClave[l] = armadura.tipo === 'sostenidos' ? 1 : -1; });

  // Paso diatónico respecto a la primera línea de la clave (el contrabajo usa la octava escrita).
  const pasoDe = (letra, octava) => (octava * 7 + LETRAS.indexOf(letra)) - (CLAVE.base[1] * 7 + LETRAS.indexOf(CLAVE.base[0]));
  const paso = (n) => pasoDe(n.letra, n.octava_escrita ?? n.octava);
  const pasoArmadura = (l) => pasoDe(l, ARM_OCTAVA[armadura.tipo][l] - CLAVE.bajar);

  let svgPentagrama = null;
  const grupos = [];

  function dibujarPentagrama() {
    const pasos = NOTAS.map(paso);
    const arriba = Math.max(0, Math.max(...pasos) - 8) * (SP / 2) + SP * 2.2;
    const abajo = Math.max(0, -Math.min(...pasos)) * (SP / 2) + SP * 3.4;
    const yLineaInferior = arriba + 4 * SP;
    const y = (s) => yLineaInferior - s * (SP / 2);

    const xArmadura = 52;
    const xNotas = xArmadura + (armadura.letras || []).length * 13 + 30;
    const ancho = xNotas + NOTAS.length * PASO_NOTA + 10;
    const alto = arriba + 4 * SP + abajo;

    const partes = [];
    for (let i = 0; i < 5; i++) {
      partes.push(`<line x1="0" x2="${ancho}" y1="${y(2 * i)}" y2="${y(2 * i)}" stroke="currentColor" stroke-width="1.1" opacity=".75"/>`);
    }
    partes.push(`<line x1="${ancho - 1}" x2="${ancho - 1}" y1="${y(8)}" y2="${y(0)}" stroke="currentColor" stroke-width="1.2" opacity=".75"/>`);
    partes.push(`<text class="smufl" x="6" y="${y(CLAVE.linea)}" font-size="${4 * SP}" fill="currentColor">${CLAVE.glifo}</text>`);

    (armadura.letras || []).forEach((l, i) => {
      const s = pasoArmadura(l);
      partes.push(`<text class="smufl" x="${xArmadura + i * 13}" y="${y(s)}" font-size="${4 * SP}" fill="currentColor">${armadura.tipo === 'sostenidos' ? G.sostenido : G.bemol}</text>`);
    });

    NOTAS.forEach((n, i) => {
      const s = pasos[i];
      const x = xNotas + i * PASO_NOTA;
      const g = [];
      g.push(`<circle class="halo" cx="${x + 7}" cy="${y(s)}" r="${SP * 1.6}" fill="transparent"/>`);
      for (let k = -2; k >= s; k -= 2) g.push(`<line x1="${x - 6}" x2="${x + 20}" y1="${y(k)}" y2="${y(k)}" stroke="currentColor" stroke-width="1.3"/>`);
      for (let k = 10; k <= s; k += 2) g.push(`<line x1="${x - 6}" x2="${x + 20}" y1="${y(k)}" y2="${y(k)}" stroke="currentColor" stroke-width="1.3"/>`);
      const alter = Number(n.alteracion) || 0;
      if (alter !== (alterClave[n.letra] || 0)) {
        g.push(`<text class="smufl" x="${x - 15}" y="${y(s)}" font-size="${4 * SP}" fill="currentColor">${ALTERACION[alter]}</text>`);
      }
      g.push(`<text class="smufl" x="${x}" y="${y(s)}" font-size="${4 * SP}" fill="currentColor">${G.cabezaNegra}</text>`);
      g.push(`<text x="${x + 7}" y="${alto - 8}" font-size="12" font-weight="700" text-anchor="middle" fill="currentColor" font-family="Nunito, sans-serif">${esc(n.nombre)}</text>`);
      grupos.push({ html: `<g class="nota nota-oculta" data-i="${i}">${g.join('')}</g>`, x });
    });

    $('pentagrama').innerHTML = `<svg xmlns="${ns}" width="${ancho}" height="${alto}" viewBox="0 0 ${ancho} ${alto}">${partes.join('')}${grupos.map((g) => g.html).join('')}</svg>`;
    svgPentagrama = $('pentagrama').querySelector('svg');
  }

  function actualizarPentagrama(actual) {
    if (!svgPentagrama) return;
    svgPentagrama.querySelectorAll('.nota').forEach((g) => {
      const i = Number(g.dataset.i);
      g.classList.toggle('nota-hecha', i < actual);
      g.classList.toggle('nota-actual', i === actual);
      g.classList.toggle('nota-oculta', i > actual);
    });
    const cont = $('pentagrama-scroll');
    const g = grupos[Math.min(actual, grupos.length - 1)];
    if (cont && g && cont.scrollWidth > cont.clientWidth) {
      // Solo se desplaza cuando la nota se acerca al borde derecho: la clave queda visible al empezar.
      const objetivo = Math.max(0, g.x - cont.clientWidth * 0.6);
      if (g.x > cont.scrollLeft + cont.clientWidth * 0.8 || g.x < cont.scrollLeft + 40) cont.scrollLeft = objetivo;
    }
  }

  /* ---------------------------------------------------------- ritmo de la nota */

  const RSP = 10;
  const ANCHO_FIG = { redonda: 46, blanca_puntillo: 46, blanca: 36, negra_puntillo: 36, negra: 30, corchea_puntillo: 32, corchea: 26, semicorchea: 22 };
  const DUR = E.figuras || {};
  const CON_BARRA = ['corchea', 'corchea_puntillo', 'semicorchea'];

  function dibujarRitmo(figuras) {
    const linea = 64;
    const tope = linea - 3.5 * RSP;
    const partes = [`<line x1="0" x2="W" y1="${linea}" y2="${linea}" stroke="currentColor" stroke-width="1.1" opacity=".55"/>`];
    const notas = [];
    let x = 10;
    let pos = 0;
    figuras.forEach((f) => {
      notas.push({ f, x, pos, stemX: x + 1.18 * RSP - 0.5 });
      pos += Number(DUR[f]) || 1;
      x += ANCHO_FIG[f] || 28;
    });
    const ancho = x + 4;

    // Grupos con barra: figuras de corchea o menores dentro del mismo pulso.
    const grupoDe = new Array(notas.length).fill(-1);
    let g = 0;
    for (let i = 0; i < notas.length; i++) {
      if (!CON_BARRA.includes(notas[i].f)) continue;
      const pulso = Math.floor(notas[i].pos + 1e-6);
      let j = i;
      while (j + 1 < notas.length && CON_BARRA.includes(notas[j + 1].f) && Math.floor(notas[j + 1].pos + 1e-6) === pulso) j++;
      if (j > i) { for (let k = i; k <= j; k++) grupoDe[k] = g; g++; i = j; }
    }

    notas.forEach((n, i) => {
      const cabeza = n.f === 'redonda' ? G.cabezaRedonda : (n.f.startsWith('blanca') ? G.cabezaBlanca : G.cabezaNegra);
      partes.push(`<text class="smufl" x="${n.x}" y="${linea}" font-size="${4 * RSP}" fill="currentColor">${cabeza}</text>`);
      if (n.f.endsWith('_puntillo')) {
        partes.push(`<text class="smufl" x="${n.x + (n.f === 'redonda' ? 1.9 : 1.5) * RSP}" y="${linea - RSP / 2}" font-size="${4 * RSP}" fill="currentColor">${G.puntillo}</text>`);
      }
      if (n.f !== 'redonda') {
        partes.push(`<line x1="${n.stemX}" x2="${n.stemX}" y1="${linea - 0.168 * RSP}" y2="${tope}" stroke="currentColor" stroke-width="1.15"/>`);
      }
      if (CON_BARRA.includes(n.f) && grupoDe[i] === -1) {
        partes.push(`<text class="smufl" x="${n.stemX - 0.55}" y="${tope}" font-size="${4 * RSP}" fill="currentColor">${n.f === 'semicorchea' ? G.corchete16 : G.corchete8}</text>`);
      }
    });

    // Barras
    const grosor = 0.5 * RSP;
    for (let k = 0; k < g; k++) {
      const idx = notas.map((_, i) => i).filter((i) => grupoDe[i] === k);
      const a = notas[idx[0]];
      const b = notas[idx[idx.length - 1]];
      partes.push(`<rect x="${a.stemX - 0.6}" y="${tope}" width="${b.stemX - a.stemX + 1.2}" height="${grosor}" fill="currentColor"/>`);
      const y2 = tope + grosor + 0.25 * RSP;
      idx.forEach((i, p) => {
        if (notas[i].f !== 'semicorchea') return;
        const sig = idx[p + 1] !== undefined && notas[idx[p + 1]].f === 'semicorchea';
        const ant = p > 0 && notas[idx[p - 1]].f === 'semicorchea';
        if (sig) {
          partes.push(`<rect x="${notas[i].stemX - 0.6}" y="${y2}" width="${notas[idx[p + 1]].stemX - notas[i].stemX + 1.2}" height="${grosor}" fill="currentColor"/>`);
        } else if (!ant) {
          const largo = 0.95 * RSP;
          const haciaAtras = p === idx.length - 1;
          const x0 = haciaAtras ? notas[i].stemX - largo : notas[i].stemX - 0.6;
          partes.push(`<rect x="${x0}" y="${y2}" width="${largo + 0.6}" height="${grosor}" fill="currentColor"/>`);
        }
      });
    }

    const html = partes.join('').replace('x2="W"', `x2="${ancho}"`);
    $('ritmo-svg').innerHTML = `<svg xmlns="${ns}" width="${ancho}" height="84" viewBox="0 0 ${ancho} 84">${html}</svg>`;
    $('ritmo-texto').textContent = textoCelda(figuras);
  }

  /* ---------------------------------------------------------- nota objetivo */

  const estado = {
    i: 0,
    terminado: false,
    inicio: null,           // ms del inicio de la escala
    inicioNota: null,
    sostenido: 0,
    fuera: 0,
    muestras: [],
    resultados: [],
  };

  function digitacion(n) {
    if (!n.cuerda) return '';
    const dedo = Number(n.dedo);
    return `Cuerda ${n.cuerda} · ${dedo === 0 ? 'al aire' : `dedo ${dedo}${n.variante ? ` ${n.variante}` : ''}`}`;
  }

  function mostrarObjetivo() {
    const n = NOTAS[Math.min(estado.i, NOTAS.length - 1)];
    $('objetivo-nombre').textContent = n.etiqueta;
    $('objetivo-hz').textContent = Number(n.frecuencia).toFixed(2);
    $('objetivo-digitacion').textContent = digitacion(n);
    $('paso-actual').textContent = `Nota ${Math.min(estado.i + 1, NOTAS.length)}`;
    dibujarRitmo(RITMO[estado.i] || RITMO[0] || ['negra']);
    actualizarPentagrama(estado.terminado ? NOTAS.length : estado.i);
    $('objetivo-puntos').querySelectorAll('span').forEach((p, i) => {
      p.classList.toggle('hecha', i < estado.i);
      p.classList.toggle('actual', i === estado.i && !estado.terminado);
    });
    $('sostener-barra').style.width = '0';
  }

  function completarNota(cents) {
    const ahora = performance.now();
    estado.resultados.push({
      cents: Math.round(cents * 10) / 10,
      ms: Math.round(ahora - (estado.inicioNota ?? ahora)),
    });
    estado.i += 1;
    estado.sostenido = 0;
    estado.fuera = 0;
    estado.muestras = [];
    estado.inicioNota = ahora;

    const card = $('objetivo-card');
    card.classList.remove('acierto');
    void card.offsetWidth; // reinicia la animación
    card.classList.add('acierto');

    if (estado.i >= NOTAS.length) {
      terminar();
      return;
    }
    mostrarObjetivo();
  }

  function terminar() {
    estado.terminado = true;
    actualizarPentagrama(NOTAS.length);
    $('objetivo-puntos').querySelectorAll('span').forEach((p) => { p.classList.add('hecha'); p.classList.remove('actual'); });
    $('paso-actual').textContent = `Nota ${NOTAS.length}`;
    lectura(ES_TECNICA ? 'Ejercicio completado.' : 'Escala completada.', 'ok');
    detenerMicrofono();

    estado.duracion = Math.round((performance.now() - estado.inicio) / 1000);
    const total = estado.duracion;
    if (ES_VELOCIDAD) evaluarVelocidad();
    const desvio = estado.resultados.reduce((a, r) => a + Math.abs(r.cents), 0) / estado.resultados.length;
    let dificil = 0;
    estado.resultados.forEach((r, i) => { if (r.ms > estado.resultados[dificil].ms) dificil = i; });
    const min = Math.floor(total / 60);
    const seg = total % 60;
    $('resultado-stats').innerHTML = `
      <div><strong>${min ? `${min} min ${seg} s` : `${seg} s`}</strong><span>Tiempo total</span></div>
      <div><strong>${desvio.toFixed(1)} cents</strong><span>Desvío medio al afinar</span></div>
      <div><strong>${esc(NOTAS[dificil].etiqueta)}</strong><span>La nota que más te costó (${(estado.resultados[dificil].ms / 1000).toFixed(1)} s)</span></div>`;
    const card = $('resultado-card');
    card.classList.remove('hidden');
    card.focus({ preventScroll: true });
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    UI.celebrar?.();
  }

  /** ¿Se tocó al tempo? Compara el tiempo real (desde la primera nota) con el escrito al tempo actual. */
  function evaluarVelocidad() {
    const caja = $('velocidad-resultado');
    if (!caja) return;
    const bpm = metro.bpm;
    const pulsos = RITMO.slice(0, -1).reduce((a, celda) => a + celda.reduce((b, f) => b + (Number(DUR[f]) || 1), 0), 0);
    const esperado = (pulsos * 60) / bpm;
    const real = (performance.now() - (estado.inicioReal ?? estado.inicio)) / 1000;
    const aTiempo = real <= esperado * 1.3 + 0.5;
    if (aTiempo) estado.bpmLogrado = Math.max(estado.bpmLogrado || 0, bpm);
    const siguiente = Math.min(VEL.bpm_objetivo, bpm + VEL.paso);
    const meta = aTiempo && bpm >= VEL.bpm_objetivo;
    caja.innerHTML = `
      <p class="${aTiempo ? 'ok' : 'aviso'}"><strong>${aTiempo ? 'A tempo' : 'Más lento que el metrónomo'}:</strong>
        tocaste las notas en ${real.toFixed(1).replace('.', ',')} s; a ${bpm} el ejercicio dura ${esperado.toFixed(1).replace('.', ',')} s.</p>
      ${meta ? `<p class="ok"><strong>Meta de tempo alcanzada (${VEL.bpm_objetivo}).</strong></p>`
        : `<button class="btn-msea" type="button" id="btn-subir-tempo">${aTiempo ? `Subir a ${siguiente} y repetir` : `Repetir a ${bpm}`}</button>`}`;
    caja.classList.remove('hidden');
    $('btn-subir-tempo')?.addEventListener('click', () => {
      if (aTiempo) fijarBpm(siguiente);
      reiniciar();
      activarMicrofono();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  function reiniciar() {
    Object.assign(estado, { inicioReal: null, duracion: null, i: 0, terminado: false, inicio: microfono.activo ? performance.now() : null, inicioNota: microfono.activo ? performance.now() : null, sostenido: 0, fuera: 0, muestras: [], resultados: [] });
    $('resultado-card').classList.add('hidden');
    mostrarObjetivo();
    lectura(microfono.activo ? 'Toca la nota objetivo.' : 'Activa el micrófono para empezar.');
    reiniciarLectura();
  }

  /* ---------------------------------------------------------- afinador: dial, lectura e historial */

  const YIN = window.MSEA_YIN;
  const PERFIL = YIN.PERFILES[(E.instrumento || {}).clave] || YIN.PERFILES.violin;
  const DIAL = { cx: 160, cy: 170, r: 138, grados: 62 };   // ±50 cents = ±62°
  const NOMBRES = ['Do', 'Do♯', 'Re', 'Re♯', 'Mi', 'Fa', 'Fa♯', 'Sol', 'Sol♯', 'La', 'La♯', 'Si'];
  const vista = { suave: null, midi: null };                 // aguja suavizada (como un afinador de aguja)
  const historial = [];                                     // { t, c, ok } de los últimos segundos
  const HISTORIAL_MS = 6000;

  const anguloDe = (c) => (Math.max(-50, Math.min(50, c)) * DIAL.grados) / 50;
  function punto(angulo, radio) {
    const rad = ((angulo - 90) * Math.PI) / 180;
    return [DIAL.cx + radio * Math.cos(rad), DIAL.cy + radio * Math.sin(rad)];
  }
  function arco(a1, a2, radio) {
    const [x1, y1] = punto(a1, radio);
    const [x2, y2] = punto(a2, radio);
    return `M${x1.toFixed(2)} ${y1.toFixed(2)} A${radio} ${radio} 0 ${Math.abs(a2 - a1) > 180 ? 1 : 0} 1 ${x2.toFixed(2)} ${y2.toFixed(2)}`;
  }

  function dibujarDial() {
    const p = [];
    p.push(`<path d="${arco(-DIAL.grados - 4, DIAL.grados + 4, DIAL.r)}" class="dial-arco" />`);
    p.push(`<path d="${arco(anguloDe(-TOL), anguloDe(TOL), DIAL.r - 9)}" class="dial-zona" />`);
    for (let c = -50; c <= 50; c += 5) {
      const mayor = c % 10 === 0;
      const [x1, y1] = punto(anguloDe(c), DIAL.r - (c === 0 ? 26 : mayor ? 18 : 10));
      const [x2, y2] = punto(anguloDe(c), DIAL.r);
      p.push(`<line x1="${x1.toFixed(2)}" y1="${y1.toFixed(2)}" x2="${x2.toFixed(2)}" y2="${y2.toFixed(2)}" class="dial-marca ${mayor ? 'mayor' : ''} ${c === 0 ? 'centro' : ''}" />`);
      if (c % 25 === 0) {
        const [tx, ty] = punto(anguloDe(c), DIAL.r + 13);
        p.push(`<text x="${tx.toFixed(1)}" y="${(ty + 4).toFixed(1)}" class="dial-texto">${c > 0 ? '+' : c < 0 ? '−' : ''}${Math.abs(c)}</text>`);
      }
    }
    p.push(`<text x="${DIAL.cx - DIAL.r + 2}" y="${DIAL.cy + 14}" class="dial-texto lado">grave</text>`);
    p.push(`<text x="${DIAL.cx + DIAL.r - 2}" y="${DIAL.cy + 14}" class="dial-texto lado">aguda</text>`);
    p.push(`<g id="dial-aguja" class="dial-aguja"><line x1="${DIAL.cx}" y1="${DIAL.cy}" x2="${DIAL.cx}" y2="${DIAL.cy - DIAL.r + 6}" /><circle cx="${DIAL.cx}" cy="${DIAL.cy}" r="7" /></g>`);
    $('dial').innerHTML = p.join('');
  }

  /** Mueve la aguja. c = cents respecto a la nota más cercana (como un afinador cromático). */
  function aguja(c, estadoColor = 'quieto') {
    const g = $('dial-aguja');
    $('afinador').dataset.estado = estadoColor;
    if (c === null) {
      vista.suave = null;
      if (g) g.style.transform = 'rotate(0deg)';
      return;
    }
    vista.suave = vista.suave === null ? c : vista.suave + 0.35 * (c - vista.suave);
    if (g) g.style.transform = `rotate(${anguloDe(vista.suave).toFixed(2)}deg)`;
  }

  function lecturaDetectada(f, nombre, c) {
    $('af-nota').textContent = nombre;
    const redondo = Math.round(c);
    $('af-cents').textContent = `${redondo > 0 ? '+' : redondo < 0 ? '−' : ''}${Math.abs(redondo)}`;
    $('af-hz').textContent = f.toFixed(2);
  }

  function lecturaVacia() {
    $('af-nota').textContent = '--';
    $('af-cents').textContent = '0';
    $('af-hz').textContent = '--';
  }

  function lectura(texto, tipo = '') {
    const el = $('lectura');
    el.textContent = texto;
    el.className = `lectura ${tipo}`;
  }

  function nombreDe(midi) {
    return `${NOMBRES[((midi % 12) + 12) % 12]}${Math.floor(midi / 12) - 1}`;
  }

  function nivel(rms) {
    const db = 20 * Math.log10(Math.max(rms, 1e-5));          // -100 a 0 dBFS
    $('af-nivel').style.width = `${Math.max(0, Math.min(100, ((db + 60) / 60) * 100))}%`;
  }

  /** Historial: línea de afinación respecto a la nota objetivo durante los últimos segundos. */
  function pintarHistorial() {
    const canvas = $('historial');
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const ancho = canvas.clientWidth;
    const alto = canvas.clientHeight;
    if (canvas.width !== Math.round(ancho * dpr)) { canvas.width = Math.round(ancho * dpr); canvas.height = Math.round(alto * dpr); }
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, ancho, alto);
    const estilo = getComputedStyle(canvas);
    const y = (c) => alto / 2 - (Math.max(-50, Math.min(50, c)) / 50) * (alto / 2 - 6);

    ctx.fillStyle = estilo.getPropertyValue('--zona').trim() || 'rgba(140,198,63,.22)';
    ctx.fillRect(0, y(TOL), ancho, y(-TOL) - y(TOL));
    ctx.strokeStyle = estilo.getPropertyValue('--guia').trim() || 'rgba(255,255,255,.18)';
    ctx.lineWidth = 1;
    [-25, 0, 25].forEach((c) => { ctx.beginPath(); ctx.moveTo(0, y(c)); ctx.lineTo(ancho, y(c)); ctx.stroke(); });

    const ahora = performance.now();
    while (historial.length && ahora - historial[0].t > HISTORIAL_MS) historial.shift();
    const x = (t) => ancho - ((ahora - t) / HISTORIAL_MS) * ancho;
    ctx.lineWidth = 2.5;
    ctx.lineJoin = 'round';
    for (let i = 1; i < historial.length; i++) {
      const a = historial[i - 1];
      const b = historial[i];
      if (b.t - a.t > 250) continue;                           // silencio: se corta la línea
      ctx.strokeStyle = b.ok ? (estilo.getPropertyValue('--bien').trim() || '#8CC63F') : (estilo.getPropertyValue('--mal').trim() || '#E6B84A');
      ctx.beginPath(); ctx.moveTo(x(a.t), y(a.c)); ctx.lineTo(x(b.t), y(b.c)); ctx.stroke();
    }
  }

  function bucleHistorial() {
    pintarHistorial();
    if (microfono.activo) requestAnimationFrame(bucleHistorial);
  }

  function reiniciarLectura() {
    aguja(null);
    lecturaVacia();
    historial.length = 0;
    pintarHistorial();
  }

  /* ---------------------------------------------------------- audio compartido */

  let ctxAudio = null;
  function audio() {
    ctxAudio ||= new (window.AudioContext || window.webkitAudioContext)();
    if (ctxAudio.state === 'suspended') ctxAudio.resume();
    return ctxAudio;
  }

  /* ---------------------------------------------------------- micrófono y detección (YIN, ver afinador-yin.js) */

  const microfono = { activo: false, stream: null, analizador: null, buffer: null, ultimo: 0, ultimaDeteccion: 0, lecturas: [], silenciarHasta: 0, wake: null };

  async function activarMicrofono() {
    if (!navigator.mediaDevices?.getUserMedia) {
      lectura('Tu navegador no permite usar el micrófono.', 'aviso');
      return;
    }
    try {
      microfono.stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false } });
    } catch (e) {
      lectura(e && e.name === 'NotFoundError' ? 'No encontramos un micrófono en este dispositivo.' : 'Permiso de micrófono denegado. Actívalo en el navegador para practicar.', 'aviso');
      return;
    }
    const ctx = audio();
    microfono.analizador = ctx.createAnalyser();
    microfono.analizador.fftSize = PERFIL.ventana;              // 4096 (violín, viola) u 8192 (violonchelo, contrabajo)
    ctx.createMediaStreamSource(microfono.stream).connect(microfono.analizador);
    microfono.buffer = new Float32Array(microfono.analizador.fftSize);
    microfono.activo = true;
    microfono.ultimo = performance.now();
    microfono.lecturas = [];
    try { microfono.wake = await navigator.wakeLock?.request('screen'); } catch (e) { microfono.wake = null; }

    if (estado.terminado) reiniciar();
    estado.inicio ??= performance.now();
    estado.inicioNota ??= performance.now();
    $('btn-microfono').innerHTML = `${UI.icon('detener')} Detener micrófono`;
    lectura(ES_VELOCIDAD ? 'Toca el ejercicio siguiendo el metrónomo.' : 'Toca la nota objetivo.');
    if (ES_VELOCIDAD && !metro.activo) alternarMetro();
    requestAnimationFrame(bucle);
    requestAnimationFrame(bucleHistorial);
  }

  function detenerMicrofono() {
    microfono.activo = false;
    microfono.stream?.getTracks().forEach((t) => t.stop());
    microfono.stream = null;
    try { microfono.wake?.release(); } catch (e) { /* sin bloqueo de pantalla */ }
    $('btn-microfono').innerHTML = `${UI.icon('microfono')} ${estado.terminado ? 'Tocar otra vez' : 'Continuar con el micrófono'}`;
    aguja(null);
    $('af-nivel').style.width = '0';
  }

  function bucle(t) {
    if (!microfono.activo) return;
    requestAnimationFrame(bucle);
    if (t - microfono.ultimaDeteccion < 30) return;           // ~30 lecturas por segundo
    const dt = Math.min(100, t - microfono.ultimo);
    microfono.ultimo = t;
    microfono.ultimaDeteccion = t;

    microfono.analizador.getFloatTimeDomainData(microfono.buffer);
    let rms = 0;
    for (let i = 0; i < microfono.buffer.length; i++) rms += microfono.buffer[i] * microfono.buffer[i];
    rms = Math.sqrt(rms / microfono.buffer.length);
    nivel(rms);

    let r = null;
    if (performance.now() >= microfono.silenciarHasta && rms > 0.006) r = YIN.detectarPara(INST.clave, microfono.buffer, ctxAudio.sampleRate);

    if (r) {
      microfono.lecturas.push(r.frecuencia);
      if (microfono.lecturas.length > 5) microfono.lecturas.shift();
    } else {
      microfono.lecturas = [];
    }

    if (microfono.lecturas.length >= 3) {
      const orden = [...microfono.lecturas].sort((x, y) => x - y);
      procesar(orden[Math.floor(orden.length / 2)], dt);
    } else {
      sinSenal(dt, performance.now() < microfono.silenciarHasta);
    }
  }

  /** Tiempo afinado necesario para avanzar: medio segundo, o una parte de la figura al tempo actual. */
  function sostenerMs() {
    if (!ES_VELOCIDAD) return SOSTENER_MS;
    const figuras = RITMO[estado.i] || ['negra'];
    const pulsos = figuras.reduce((a, f) => a + (Number(DUR[f]) || 1), 0);
    return Math.max(45, Math.min(300, 0.35 * pulsos * (60000 / metro.bpm)));
  }

  function sinSenal(dt, escuchandoReferencia) {
    estado.fuera += dt;
    if (estado.fuera > GRACIA_MS) {
      estado.sostenido = 0;
      estado.muestras = [];
      $('sostener-barra').style.width = '0';
      aguja(null);
      lecturaVacia();
      lectura(escuchandoReferencia ? 'Escucha la nota de referencia...' : `No se escucha ${INST.clave === 'viola' ? 'la viola' : `el ${String(INST.nombre).toLowerCase()}`}. Toca la nota objetivo.`);
    }
  }

  function procesar(f, dt) {
    if (estado.terminado) return;
    const objetivo = NOTAS[estado.i];
    const cercana = YIN.notaCercana(f);
    const cObjetivo = YIN.cents(f, objetivo.frecuencia);
    const esObjetivo = cercana.midi === objetivo.midi;
    const afinada = esObjetivo && Math.abs(cObjetivo) <= TOL;

    // La nota detectada se escribe como en la escala (Mi♭ y no Re♯) cuando es la objetivo.
    if (vista.midi !== cercana.midi) { vista.midi = cercana.midi; vista.suave = null; }
    lecturaDetectada(f, esObjetivo ? objetivo.etiqueta : nombreDe(cercana.midi), cercana.cents);
    aguja(cercana.cents, afinada ? 'afinada' : (esObjetivo && Math.abs(cObjetivo) <= 2 * TOL ? 'cerca' : 'lejos'));
    historial.push({ t: performance.now(), c: Math.max(-60, Math.min(60, cObjetivo)), ok: afinada });

    if (afinada) {
      estado.fuera = 0;
      estado.sostenido += dt;
      estado.muestras.push(cObjetivo);
      lectura('Afinada. Mantenla...', 'ok');
      if (estado.i === 0 && !estado.inicioReal) estado.inicioReal = performance.now() - dt;
      const necesario = sostenerMs();
      $('sostener-barra').style.width = `${Math.min(100, (estado.sostenido * 100) / necesario)}%`;
      if (estado.sostenido >= necesario) {
        const media = estado.muestras.reduce((a, b) => a + b, 0) / estado.muestras.length;
        completarNota(media);
      }
      return;
    }

    estado.fuera += dt;
    if (estado.fuera > GRACIA_MS) {
      estado.sostenido = 0;
      estado.muestras = [];
      $('sostener-barra').style.width = '0';
    }

    const diferencia = cercana.midi - objetivo.midi;
    if (esObjetivo) {
      lectura(cObjetivo < 0 ? 'Un poco grave: sube' : 'Un poco aguda: baja', 'aviso');
    } else if (diferencia % 12 === 0) {
      lectura(`Es ${objetivo.nombre}, pero una octava más ${diferencia > 0 ? 'aguda' : 'grave'}. Busca ${objetivo.etiqueta}.`, 'aviso');
    } else {
      lectura(`Busca ${objetivo.etiqueta}: ${diferencia < 0 ? 'sube' : 'baja'} ${Math.abs(diferencia) === 1 ? 'un semitono' : `${Math.abs(diferencia)} semitonos`}.`, 'aviso');
    }
  }

  /** Nota de referencia con la frecuencia exacta. El micrófono la ignora mientras suena. */
  function escucharNota() {
    const n = NOTAS[Math.min(estado.i, NOTAS.length - 1)];
    const ctx = audio();
    const t0 = ctx.currentTime + 0.02;
    const osc = ctx.createOscillator();
    const gan = ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.value = n.frecuencia;
    gan.gain.setValueAtTime(0.0001, t0);
    gan.gain.exponentialRampToValueAtTime(0.25, t0 + 0.04);
    gan.gain.setValueAtTime(0.25, t0 + 1.0);
    gan.gain.exponentialRampToValueAtTime(0.0001, t0 + 1.4);
    osc.connect(gan).connect(ctx.destination);
    osc.start(t0);
    osc.stop(t0 + 1.45);
    microfono.silenciarHasta = performance.now() + 1750;
  }

  /* ---------------------------------------------------------- metrónomo (programado con Web Audio) */

  const PULSOS = { '2/4': 2, '3/4': 3, '4/4': 4, '2/2': 2, '6/8': 6 };
  const UNIDAD = { '2/2': 'blanca', '6/8': 'corchea' };
  const metro = { activo: false, bpm: Number(E.config.bpm) || 60, compas: E.config.compas || '4/4', siguiente: 0, pulso: 0, timer: null, cola: [], taps: [] };

  function pintarPulsos() {
    const n = PULSOS[metro.compas] || 4;
    $('metro-pulsos').innerHTML = Array.from({ length: n }, (_, i) => `<span class="${i === 0 ? 'fuerte' : (metro.compas === '6/8' && i === 3 ? 'medio' : '')}"></span>`).join('');
    $('metro-unidad').textContent = `Cada clic es una ${UNIDAD[metro.compas] || 'negra'}.`;
  }

  function fijarBpm(v) {
    metro.bpm = Math.max(30, Math.min(240, Math.round(Number(v) || 60)));
    $('metro-bpm').value = String(metro.bpm);
    $('metro-bpm-valor').textContent = String(metro.bpm);
  }

  function clic(t, pulso) {
    const ctx = audio();
    const fuerte = pulso === 0 && $('metro-acento').checked;
    const medio = metro.compas === '6/8' && pulso === 3 && $('metro-acento').checked;
    const osc = ctx.createOscillator();
    const gan = ctx.createGain();
    osc.frequency.value = fuerte ? 1560 : (medio ? 1240 : 980);
    gan.gain.setValueAtTime(0.0001, t);
    gan.gain.exponentialRampToValueAtTime(fuerte ? 0.6 : 0.38, t + 0.002);
    gan.gain.exponentialRampToValueAtTime(0.0001, t + 0.05);
    osc.connect(gan).connect(ctx.destination);
    osc.start(t);
    osc.stop(t + 0.06);
  }

  function programar() {
    const ctx = audio();
    while (metro.siguiente < ctx.currentTime + 0.12) {
      clic(metro.siguiente, metro.pulso);
      metro.cola.push({ t: metro.siguiente, pulso: metro.pulso });
      metro.siguiente += 60 / metro.bpm;
      metro.pulso = (metro.pulso + 1) % (PULSOS[metro.compas] || 4);
    }
  }

  function dibujarMetro() {
    if (!metro.activo) return;
    const ahora = audio().currentTime;
    let ultimo = null;
    while (metro.cola.length && metro.cola[0].t <= ahora) ultimo = metro.cola.shift();
    if (ultimo) {
      $('metro-pulsos').querySelectorAll('span').forEach((s, i) => s.classList.toggle('activo', i === ultimo.pulso));
    }
    requestAnimationFrame(dibujarMetro);
  }

  function alternarMetro() {
    const btn = $('metro-iniciar');
    if (metro.activo) {
      metro.activo = false;
      clearInterval(metro.timer);
      metro.cola = [];
      $('metro-pulsos').querySelectorAll('span').forEach((s) => s.classList.remove('activo'));
      btn.innerHTML = `${UI.icon('play')} Iniciar metrónomo`;
      return;
    }
    const ctx = audio();
    metro.activo = true;
    metro.pulso = 0;
    metro.siguiente = ctx.currentTime + 0.08;
    programar();
    metro.timer = setInterval(programar, 25);
    requestAnimationFrame(dibujarMetro);
    btn.innerHTML = `${UI.icon('pausa')} Detener metrónomo`;
  }

  function initMetronomo() {
    pintarPulsos();
    fijarBpm(metro.bpm);
    $('metro-bpm').addEventListener('input', (e) => fijarBpm(e.target.value));
    document.querySelectorAll('[data-bpm]').forEach((b) => b.addEventListener('click', () => fijarBpm(metro.bpm + Number(b.dataset.bpm))));
    $('metro-compas').addEventListener('change', (e) => {
      metro.compas = e.target.value;
      metro.pulso = 0;
      pintarPulsos();
    });
    $('metro-iniciar').addEventListener('click', alternarMetro);
    $('metro-restaurar')?.addEventListener('click', () => {
      fijarBpm(E.config.bpm);
      metro.compas = E.config.compas;
      $('metro-compas').value = metro.compas;
      metro.pulso = 0;
      pintarPulsos();
    });
    $('metro-tap').addEventListener('click', () => {
      const ahora = performance.now();
      metro.taps = metro.taps.filter((t) => ahora - t < 2500);
      metro.taps.push(ahora);
      if (metro.taps.length >= 2) {
        const ult = metro.taps.slice(-5);
        const media = (ult[ult.length - 1] - ult[0]) / (ult.length - 1);
        fijarBpm(60000 / media);
      }
    });
  }

  /* ---------------------------------------------------------- entrega */

  function initEntrega() {
    const form = $('form-entregar');
    document.querySelectorAll('[data-repetir]').forEach((b) => b.addEventListener('click', () => {
      reiniciar();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
    if (!form || !URL_ENTREGA) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!estado.terminado) return;
      const datos = new FormData(form);
      try {
        const r = await conCarga($('btn-entregar'), api(URL_ENTREGA, {
          json: {
            resultado: {
              notas: estado.resultados,
              duracion_s: estado.duracion ?? Math.round((performance.now() - estado.inicio) / 1000),
              bpm: ES_VELOCIDAD ? (estado.bpmLogrado || null) : null,
            },
            comentario: datos.get('comentario') || null,
            instrumento: INST.clave,
          },
        }));
        toast(r.message || 'Entregado.');
        form.innerHTML = `<p class="hint">${UI.icon('check-circulo', 'icon-green')} ${esc(r.resumen || 'Entregada.')} Tu profesor recibió una notificación.</p>
          <div class="resultado-acciones"><a class="btn-msea" href="/dashboard-estudiante#tareas">Volver a mis tareas</a>
          <button class="btn-msea secondary" type="button" data-repetir>${UI.icon('refrescar')} Practicar otra vez</button></div>`;
        form.querySelector('[data-repetir]').addEventListener('click', () => { reiniciar(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
      } catch (err) {
        UI.mostrarErrores(form, err.errors);
        toast(err.message);
      }
    });
  }

  /* ---------------------------------------------------------- inicio */

  document.addEventListener('DOMContentLoaded', () => {
    $('objetivo-puntos').innerHTML = NOTAS.map(() => '<span></span>').join('');
    dibujarDial();
    pintarHistorial();
    dibujarPentagrama();
    mostrarObjetivo();
    initMetronomo();
    initEntrega();

    $('ver-completa').addEventListener('change', (e) => $('pentagrama').classList.toggle('vista-completa', e.target.checked));
    $('btn-microfono').addEventListener('click', () => (microfono.activo ? detenerMicrofono() : activarMicrofono()));
    $('btn-reiniciar').addEventListener('click', reiniciar);
    $('btn-escuchar').addEventListener('click', escucharNota);
    $('btn-simular')?.addEventListener('click', () => {
      if (estado.terminado) return;
      estado.inicio ??= performance.now();
      estado.inicioNota ??= performance.now();
      completarNota(0);
    });

    // Redibuja el pentagrama cuando la fuente musical termine de cargar (evita medidas con la fuente de reserva).
    document.fonts?.load('40px Bravura').then(() => { grupos.length = 0; dibujarPentagrama(); mostrarObjetivo(); }).catch(() => {});
  });
})();
