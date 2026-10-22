/* ============================================================
   MSEA - Revisión del digitalizador de métodos.
   Lee la notación de texto (Do#5:c, Re5:n., -:c, Si4:c/1, |) y dibuja el
   pentagrama al lado de la imagen original, para corregir lo que el
   reconocimiento leyó mal. Avisa cuando la duración no completa compases.
   ============================================================ */
(function () {
  'use strict';

  const SOLFEO = { do: 'C', re: 'D', mi: 'E', fa: 'F', sol: 'G', la: 'A', si: 'B' };
  const NOMBRE = { C: 'Do', D: 'Re', E: 'Mi', F: 'Fa', G: 'Sol', A: 'La', B: 'Si' };
  const LETRAS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
  const FIG = { r: 'redonda', b: 'blanca', n: 'negra', c: 'corchea', s: 'semicorchea' };
  const PULSOS = { redonda: 4, blanca: 2, negra: 1, corchea: 0.5, semicorchea: 0.25, blanca_puntillo: 3, negra_puntillo: 1.5, corchea_puntillo: 0.75 };
  const CLAVES = {
    sol: { glifo: '', base: ['E', 4], linea: 2, bajar: 0 },
    do: { glifo: '', base: ['F', 3], linea: 4, bajar: 1 },
    fa: { glifo: '', base: ['G', 2], linea: 6, bajar: 2 },
  };
  const ARM = { sostenidos: { F: 5, C: 5, G: 5, D: 5, A: 4, E: 5, B: 4 }, bemoles: { B: 4, E: 5, A: 4, D: 5, G: 4, C: 5, F: 4 } };
  const ORDEN = { sostenidos: ['F', 'C', 'G', 'D', 'A', 'E', 'B'], bemoles: ['B', 'E', 'A', 'D', 'G', 'C', 'F'] };
  const SP = 9;
  const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function leer(texto) {
    const tokens = texto.replace(/♯/g, '#').replace(/♭/g, 'b').split(/[\s,;]+/).filter(Boolean);
    const notas = [];
    for (const [i, t] of tokens.entries()) {
      if (t === '|' || t === '||') { notas.push({ barra: true }); continue; }
      const m = t.match(/^(?:(do|re|mi|fa|sol|la|si)(##|bb|#|b)?([0-8])|(-)):([rbncs])(\.)?(?:\/([0-4]))?$/i);
      if (!m) throw new Error(`No entiendo «${t}» (posición ${i + 1}).`);
      let figura = FIG[m[5].toLowerCase()];
      if (m[6]) figura += '_puntillo';
      if (!(figura in PULSOS)) throw new Error(`«${t}»: esa figura no admite puntillo.`);
      if (m[4]) { notas.push({ silencio: true, figura }); continue; }
      const alt = { '#': 1, '##': 2, b: -1, bb: -2 }[m[2] || ''] || 0;
      notas.push({ letra: SOLFEO[m[1].toLowerCase()], alteracion: alt, octava: Number(m[3]), figura, dedo: m[7] ?? null });
    }
    return notas;
  }

  function dibujar(caja, notas, opciones) {
    const clave = CLAVES[opciones.clave] || CLAVES.sol;
    const subir = opciones.escribe >= 12 ? 1 : 0; // contrabajo: se escribe una octava arriba
    const q = Number(opciones.fifths) || 0;
    const tipoArm = q > 0 ? 'sostenidos' : 'bemoles';
    const letrasArm = ORDEN[tipoArm].slice(0, Math.abs(q));
    const alterClave = {};
    letrasArm.forEach((l) => { alterClave[l] = q > 0 ? 1 : -1; });

    const paso = (letra, oct) => (oct * 7 + LETRAS.indexOf(letra)) - (clave.base[1] * 7 + LETRAS.indexOf(clave.base[0]));
    const pasos = notas.filter((n) => n.letra).map((n) => paso(n.letra, n.octava + subir));
    const arriba = Math.max(0, Math.max(8, ...pasos) - 8) * (SP / 2) + SP * 4.5;
    const abajo = Math.max(0, -Math.min(0, ...pasos)) * (SP / 2) + SP * 3.2;
    const base = arriba + 4 * SP;
    const y = (s) => base - s * (SP / 2);
    const xNotas = 44 + letrasArm.length * 11 + 22;
    const ancho = xNotas + notas.reduce((a, n) => a + (n.barra ? 14 : 30), 0) + 12;
    const alto = base + abajo;
    const f = 4 * SP;
    const p = [];

    for (let i = 0; i < 5; i++) p.push(`<line x1="0" x2="${ancho}" y1="${y(2 * i)}" y2="${y(2 * i)}" stroke="currentColor" stroke-width="1" opacity=".7"/>`);
    p.push(`<text class="smufl" x="4" y="${y(clave.linea)}" font-size="${f}" fill="currentColor">${clave.glifo}</text>`);
    letrasArm.forEach((l, i) => {
      p.push(`<text class="smufl" x="${40 + i * 11}" y="${y(paso(l, ARM[tipoArm][l] - clave.bajar))}" font-size="${f}" fill="currentColor">${q > 0 ? '' : ''}</text>`);
    });

    let x = xNotas;
    const enCompas = {};
    for (const n of notas) {
      if (n.barra) {
        p.push(`<line x1="${x}" x2="${x}" y1="${y(8)}" y2="${y(0)}" stroke="currentColor" stroke-width="1.1" opacity=".7"/>`);
        x += 14;
        Object.keys(enCompas).forEach((k) => delete enCompas[k]);
        continue;
      }
      const figura = n.figura.replace('_puntillo', '');
      if (n.silencio) {
        const glifo = { redonda: '', blanca: '', negra: '', corchea: '', semicorchea: '' }[figura];
        p.push(`<text class="smufl" x="${x}" y="${y(4)}" font-size="${f}" fill="currentColor" opacity=".8">${glifo}</text>`);
        x += 30;
        continue;
      }
      const s = paso(n.letra, n.octava + subir);
      for (let k = -2; k >= s; k -= 2) p.push(`<line x1="${x - 4}" x2="${x + 15}" y1="${y(k)}" y2="${y(k)}" stroke="currentColor" stroke-width="1.1"/>`);
      for (let k = 10; k <= s; k += 2) p.push(`<line x1="${x - 4}" x2="${x + 15}" y1="${y(k)}" y2="${y(k)}" stroke="currentColor" stroke-width="1.1"/>`);
      const clavePaso = `${n.letra}${n.octava}`;
      const esperado = enCompas[clavePaso] ?? (alterClave[n.letra] || 0);
      if (n.alteracion !== esperado) {
        const g = { 1: '', '-1': '', 0: '', 2: '', '-2': '' }[n.alteracion];
        p.push(`<text class="smufl" x="${x - 11}" y="${y(s)}" font-size="${f}" fill="currentColor">${g}</text>`);
      }
      enCompas[clavePaso] = n.alteracion;
      const cabeza = figura === 'redonda' ? '' : figura === 'blanca' ? '' : '';
      p.push(`<text class="smufl" x="${x}" y="${y(s)}" font-size="${f}" fill="currentColor">${cabeza}</text>`);
      if (figura !== 'redonda') {
        const abajoPlica = s >= 4;
        const xs = abajoPlica ? x + 0.6 : x + 1.18 * SP - 0.6;
        const y1 = y(s) + (abajoPlica ? 0.17 * SP : -0.17 * SP);
        const y2 = y(s) + (abajoPlica ? 3.5 * SP : -3.5 * SP);
        p.push(`<line x1="${xs}" x2="${xs}" y1="${y1}" y2="${y2}" stroke="currentColor" stroke-width="1.1"/>`);
        if (figura === 'corchea' || figura === 'semicorchea') {
          const g = abajoPlica ? (figura === 'corchea' ? '' : '') : (figura === 'corchea' ? '' : '');
          p.push(`<text class="smufl" x="${xs - 0.5}" y="${y2}" font-size="${f}" fill="currentColor">${g}</text>`);
        }
      }
      if (n.figura.endsWith('_puntillo')) p.push(`<text class="smufl" x="${x + 1.5 * SP}" y="${y(s % 2 === 0 ? s + 1 : s)}" font-size="${f}" fill="currentColor"></text>`);
      if (n.dedo !== null && n.dedo !== undefined) p.push(`<text x="${x + 5}" y="${y(Math.max(10, s + 5))}" font-size="10" font-weight="700" text-anchor="middle" fill="currentColor">${esc(n.dedo)}</text>`);
      p.push(`<text x="${x + 5}" y="${alto - 4}" font-size="9" text-anchor="middle" fill="currentColor" opacity=".7">${esc(NOMBRE[n.letra] + (n.alteracion > 0 ? '♯' : n.alteracion < 0 ? '♭' : ''))}</text>`);
      x += 30;
    }
    caja.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="${ancho}" height="${alto}" viewBox="0 0 ${ancho} ${alto}">${p.join('')}</svg>`;
  }

  function actualizar(form) {
    const caja = form.querySelector('[data-vista]');
    const resumen = form.querySelector('[data-resumen]');
    try {
      const notas = leer(form.querySelector('[data-notacion]').value);
      dibujar(caja, notas, { clave: form.dataset.clave, escribe: Number(form.dataset.escribe), fifths: form.querySelector('[data-armadura]').value });
      const sonidos = notas.filter((n) => n.letra).length;
      const total = notas.filter((n) => !n.barra).reduce((a, n) => a + PULSOS[n.figura], 0);
      const [num, den] = form.querySelector('select[name="compas"]').value.split('/').map(Number);
      const porCompas = num * (4 / den);
      const compases = total / porCompas;
      const exacto = Math.abs(compases - Math.round(compases)) < 1e-6;
      resumen.textContent = `${sonidos} notas · ${String(Math.round(total * 100) / 100).replace('.', ',')} pulsos de negra · ${exacto ? `${Math.round(compases)} compases completos` : `${compases.toFixed(2).replace('.', ',')} compases: revisa figuras, falta o sobra duración`}.`;
      resumen.classList.toggle('aviso', !exacto);
    } catch (e) {
      caja.innerHTML = `<p class="error">${esc(e.message)}</p>`;
      resumen.textContent = '';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.ejercicio-revision').forEach((form) => {
      let t = null;
      const programar = () => { clearTimeout(t); t = setTimeout(() => actualizar(form), 250); };
      form.querySelector('[data-notacion]').addEventListener('input', programar);
      form.querySelectorAll('select').forEach((s) => s.addEventListener('change', programar));
      actualizar(form);
    });
    // Redibuja cuando cargue la fuente musical.
    document.fonts?.load('36px Bravura').then(() => document.querySelectorAll('form.ejercicio-revision').forEach(actualizar)).catch(() => {});
  });
})();
