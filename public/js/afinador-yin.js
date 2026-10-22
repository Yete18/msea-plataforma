/* ============================================================
   MSEA - Detector de altura para el afinador (YIN).
   de Cheveigné y Kawahara (2002): diferencia cuadrática, normalización
   por la media acumulada, umbral absoluto y refinamiento parabólico sobre
   la diferencia sin normalizar (menos sesgo que sobre la normalizada).
   Rango por instrumento (PERFILES): violín 150-3200 Hz, viola 110-2000 Hz,
   violonchelo 55-1500 Hz y contrabajo 35-800 Hz.
   Para las notas agudas (períodos de pocas muestras) la frecuencia se refina
   midiendo varios períodos seguidos: el error se divide por la cantidad de períodos.
   Se usa en el navegador (window.MSEA_YIN) y en las pruebas con Node.
   ============================================================ */
(function (raiz) {
  'use strict';

  const UMBRAL = 0.12;

  /**
   * @param {Float32Array|number[]} buf muestras (idealmente 4096 a 44,1/48 kHz)
   * @returns {{frecuencia:number, claridad:number}|null}
   */
  function detectar(entrada, srEntrada, fMin = 150, fMax = 3200, decimar = 1) {
    // Instrumentos graves (violonchelo, contrabajo): se promedian grupos de muestras
    // (filtro paso bajo simple) para cubrir períodos largos con menos operaciones.
    let buf = entrada;
    let sr = srEntrada;
    if (decimar > 1) {
      buf = new Float32Array(Math.floor(entrada.length / decimar));
      for (let i = 0; i < buf.length; i++) {
        let suma = 0;
        for (let j = 0; j < decimar; j++) suma += entrada[i * decimar + j];
        buf[i] = suma / decimar;
      }
      sr = srEntrada / decimar;
    }
    const tauMin = Math.max(2, Math.floor(sr / fMax));
    const tauMax = Math.min(Math.ceil(sr / fMin), Math.floor(buf.length / 2) - 2);
    const W = buf.length - tauMax - 2;
    if (W <= tauMax) return null;

    // Se quita la componente continua para que no sesgue la diferencia.
    let media = 0;
    for (let i = 0; i < buf.length; i++) media += buf[i];
    media /= buf.length;

    const d = new Float64Array(tauMax + 2);
    for (let tau = 1; tau <= tauMax + 1; tau++) {
      let s = 0;
      for (let i = 0; i < W; i++) {
        const x = (buf[i] - media) - (buf[i + tau] - media);
        s += x * x;
      }
      d[tau] = s;
    }

    const cmnd = new Float64Array(tauMax + 2);
    cmnd[0] = 1;
    let acumulado = 0;
    for (let tau = 1; tau <= tauMax + 1; tau++) {
      acumulado += d[tau];
      cmnd[tau] = acumulado ? (d[tau] * tau) / acumulado : 1;
    }

    let tau = -1;
    for (let t = tauMin; t <= tauMax; t++) {
      if (cmnd[t] < UMBRAL) {
        while (t + 1 <= tauMax && cmnd[t + 1] < cmnd[t]) t++;
        tau = t;
        break;
      }
    }
    if (tau < 0) return null;

    // Notas muy agudas: con ruido, el primer período puede no bajar del umbral y se
    // toma el doble (una octava abajo). Si la mitad también es un mínimo claro, se usa.
    if (sr / tau > 900) {
      const h = Math.round(tau / 2);
      let m = h;
      for (let t = Math.max(tauMin, h - 1); t <= h + 1; t++) if (cmnd[t] < cmnd[m]) m = t;
      if (m >= tauMin && cmnd[m] < 0.3 && cmnd[m] <= cmnd[m - 1] && cmnd[m] <= cmnd[m + 1]) tau = m;
    }

    const periodo = tau + parabola(d, tau);

    // Refinamiento con k períodos seguidos (k = 2, 4, 8...): la diferencia se calcula
    // solo cerca de k·período y el error de interpolación se divide por k. Se duplica k
    // de a poco para que el centro de búsqueda nunca se aleje del mínimo real.
    let periodoFino = periodo;
    const lagMax = Math.floor(buf.length / 2);
    for (let k = 2; k * periodoFino < lagMax - 3; k *= 2) {
      const centro = Math.round(k * periodoFino);
      const W2 = buf.length - (centro + 3);
      const v = {};
      for (let t = centro - 3; t <= centro + 3; t++) {
        let s2 = 0;
        for (let i = 0; i < W2; i++) {
          const x = buf[i] - buf[i + t];
          s2 += x * x;
        }
        v[t] = s2;
      }
      let mejor = centro;
      for (let t = centro - 2; t <= centro + 2; t++) if (v[t] < v[mejor]) mejor = t;
      const den = v[mejor - 1] + v[mejor + 1] - 2 * v[mejor];
      const ajuste = den > 0 ? Math.max(-1, Math.min(1, (v[mejor - 1] - v[mejor + 1]) / (2 * den))) : 0;
      periodoFino = (mejor + ajuste) / k;
    }
    const frecuencia = sr / periodoFino;

    return { frecuencia, claridad: 1 - cmnd[tau] };
  }

  /** Desplazamiento del mínimo por interpolación parabólica (entre -1 y 1). */
  function parabola(d, t) {
    const a = d[t - 1];
    const b = d[t];
    const c = d[t + 1];
    const den = a + c - 2 * b;
    return den > 0 ? Math.max(-1, Math.min(1, (a - c) / (2 * den))) : 0;
  }

  /** Desviación en cents respecto a una frecuencia de referencia. */
  function cents(f, referencia) {
    return 1200 * Math.log2(f / referencia);
  }

  /** Nota temperada más cercana (La4 = 440 Hz). */
  function notaCercana(f, la4 = 440) {
    const midi = Math.round(12 * Math.log2(f / la4) + 69);
    const exacta = la4 * Math.pow(2, (midi - 69) / 12);
    return { midi, frecuencia: exacta, cents: cents(f, exacta) };
  }

  /** Rango de búsqueda y tamaño de ventana para cada instrumento de cuerda. */
  const PERFILES = {
    violin: { fMin: 150, fMax: 3200, decimar: 1, ventana: 4096 },
    viola: { fMin: 110, fMax: 2000, decimar: 1, ventana: 4096 },
    violonchelo: { fMin: 55, fMax: 1500, decimar: 2, ventana: 8192 },
    contrabajo: { fMin: 35, fMax: 800, decimar: 4, ventana: 8192 },
  };

  function detectarPara(instrumento, buf, sr) {
    const p = PERFILES[instrumento] || PERFILES.violin;
    return detectar(buf, sr, p.fMin, p.fMax, p.decimar);
  }

  const api = { detectar, detectarPara, PERFILES, cents, notaCercana, UMBRAL };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else raiz.MSEA_YIN = api;
})(typeof window !== 'undefined' ? window : globalThis);
