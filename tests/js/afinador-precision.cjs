/* Precisión del detector con señales sintéticas tipo violín (armónicos, vibrato opcional y ruido).
   Uso: node tests/js/afinador-precision.cjs  -> sale con código 1 si algún error supera el límite. */
// El proyecto es "type": "module": se carga el archivo del navegador tal cual, con un window simulado.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const contexto = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/afinador-yin.js'), 'utf8'), contexto);
const { detectarPara, PERFILES, cents } = contexto.window.MSEA_YIN;

function senal(f, sr, n, { ruido = 0.02, fase = Math.random() * 6.28, arm = [1, 0.6, 0.45, 0.3, 0.25, 0.18, 0.12, 0.08] } = {}) {
  const buf = new Float32Array(n); // espectro rico, como cuerda frotada
  for (let i = 0; i < n; i++) {
    let v = 0;
    for (let k = 0; k < arm.length; k++) if (f * (k + 1) < sr / 2) v += arm[k] * Math.sin(2 * Math.PI * f * (k + 1) * i / sr + fase * (k + 1));
    buf[i] = 0.25 * v + ruido * (Math.random() * 2 - 1);
  }
  return buf;
}

// Registro de cada instrumento (MIDI): de la cuerda más grave a la nota más aguda de las escalas.
const RANGOS = { violin: [55, 100], viola: [48, 93], violonchelo: [36, 88], contrabajo: [28, 72] };

const ESCENARIOS = [
  { nombre: 'Señal limpia', opciones: {}, limite: 0.5 },
  // Cuerda Sol: el 2.º armónico suena más fuerte que la fundamental; además más ruido de sala.
  { nombre: 'Fundamental débil y ruido', opciones: { ruido: 0.08, arm: [0.35, 1, 0.7, 0.5, 0.35, 0.25, 0.15, 0.1] }, limite: 1.0 },
];
let ok = true;
for (const esc of ESCENARIOS) {
  let peor = 0, total = 0, n = 0, fallos = 0;
  for (const [instrumento, [desde, hasta]] of Object.entries(RANGOS)) {
  for (const sr of [44100, 48000]) {
    for (let midi = desde; midi <= hasta; midi++) {
      for (const desvio of [-23.7, -4.2, 0, 1.3, 9.6]) {
        const f = 440 * Math.pow(2, (midi - 69) / 12) * Math.pow(2, desvio / 1200);
        const r = detectarPara(instrumento, senal(f, sr, PERFILES[instrumento].ventana, esc.opciones), sr);
        if (!r) { fallos++; continue; }
        const e = Math.abs(cents(r.frecuencia, f));
        peor = Math.max(peor, e); total += e; n++;
      }
    }
  }
  }
  const pasa = fallos === 0 && peor <= esc.limite;
  ok = ok && pasa;
  console.log(`${pasa ? 'OK   ' : 'FALLA'} ${esc.nombre}: ${n} mediciones, sin lectura ${fallos}, error medio ${(total / n).toFixed(3)} cents, peor ${peor.toFixed(3)} cents (límite ${esc.limite})`);
}
process.exit(ok ? 0 : 1);
