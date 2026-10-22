const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8125';
const OUT = process.env.OUT || 'storage/app/e2e';
async function login(p, c, k, r) {
  await p.goto(BASE + '/login');
  await p.fill('input[name="correo"]', c); await p.fill('input[name="contrasena"]', k);
  /* UX-07: el rol ya no se elige al iniciar sesión. */
  await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);
}
const micFalso = () => {
  navigator.mediaDevices.getUserMedia = async () => {
    const ctx = new AudioContext();
    const osc = ctx.createOscillator(); osc.type = 'sawtooth';
    const g = ctx.createGain(); g.gain.value = 0.0;
    const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 3500;
    const dest = ctx.createMediaStreamDestination();
    osc.connect(lp).connect(g).connect(dest); osc.start();
    window.__mic = { ctx, osc, g };
    return dest.stream;
  };
};
(async () => {
  const b = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] });
  const errs = [];
  const nuevo = async (w = 1366, h = 900) => { const c = await b.newContext({ viewport: { width: w, height: h } }); const p = await c.newPage(); p.on('pageerror', e => errs.push(String(e))); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); }); return p; };

  // ---- profesor: biblioteca y asignación con ritmo propio
  const prof = await nuevo();
  await login(prof, 'profesor@msea.test', 'profesor123', 'profesor');
  await prof.goto(BASE + '/dashboard-profesor#biblioteca'); await prof.waitForTimeout(600);
  await prof.screenshot({ path: OUT + '/1-profesor-biblioteca.png' });
  await prof.click('[data-asignar]'); await prof.waitForTimeout(500);
  await prof.screenshot({ path: OUT + '/2-modal-uniforme.png' });
  await prof.check('input[name="esc_ritmo_modo"][value="patron"]');
  await prof.click('[data-celula="0"]');
  await prof.click('#esc-agregar-celda'); await prof.click('[data-celula="1"]');
  await prof.click('#esc-agregar-celda'); await prof.click('[data-agregar="negra"]'); await prof.click('[data-agregar="negra"]');
  await prof.waitForTimeout(300);
  await prof.locator('#escala-config').screenshot({ path: OUT + '/3-modal-ritmo-propio.png' });
  // Para la prueba de punta a punta: 1 negra por nota (más rápido de leer), tolerancia 10
  await prof.check('input[name="esc_ritmo_modo"][value="uniforme"]');
  await prof.click('[data-figura="negra"][data-rep="4"]');
  await prof.selectOption('#esc-tolerancia', '10');
  await Promise.all([prof.waitForNavigation(), prof.click('#btn-guardar-tarea')]);
  await prof.waitForTimeout(500);
  console.log('tarea creada:', await prof.locator('#tareas-tbody tr').first().innerText());

  // ---- estudiante
  const est = await nuevo();
  await est.addInitScript(micFalso);
  await login(est, 'estudiante@msea.test', 'estudiante123', 'estudiante');
  await est.goto(BASE + '/dashboard-estudiante#tareas'); await est.waitForTimeout(700);
  await est.screenshot({ path: OUT + '/4-estudiante-tareas.png' });
  const href = await est.locator('a:has-text("Realizar tarea")').first().getAttribute('href');
  await est.goto(href); await est.waitForTimeout(800);
  await est.screenshot({ path: OUT + '/5-escala-inicio.png', fullPage: true });

  await est.click('#btn-microfono'); await est.waitForTimeout(300);
  const fijar = (f, vol = 0.4) => est.evaluate(([f, v]) => { window.__mic.osc.frequency.value = f; window.__mic.g.gain.value = v; }, [f, vol]);
  const hz = async () => Number(await est.locator('#objetivo-hz').innerText());
  // nota 1: 30 cents alta -> no avanza
  await fijar((await hz()) * Math.pow(2, 30 / 1200)); await est.waitForTimeout(1200);
  console.log('lectura +30c:', await est.locator('#lectura').innerText(), '|', await est.locator('#paso-actual').innerText());
  await est.screenshot({ path: OUT + '/6-desafinada.png' });
  // octava equivocada
  await fijar((await hz()) * 2); await est.waitForTimeout(900);
  console.log('lectura octava:', await est.locator('#lectura').innerText());
  const total = Number(await est.locator('#paso-total').innerText());
  const tiempos = [];
  for (let i = 0; i < total; i++) {
    const objetivo = await hz();
    const t0 = Date.now();
    await fijar(objetivo * Math.pow(2, (i % 2 ? 4 : -3) / 1200));
    if (i === 5) { await est.waitForTimeout(250); await est.screenshot({ path: OUT + '/7-afinando-nota6.png', fullPage: true }); }
    if (i < total - 1) await est.waitForFunction((n) => document.getElementById('paso-actual').textContent === `Nota ${n}`, i + 2, { timeout: 5000 });
    else await est.waitForSelector('#resultado-card:not(.hidden)', { timeout: 5000 });
    tiempos.push(Date.now() - t0);
    await fijar(objetivo, 0); await est.waitForTimeout(200); // silencio entre notas
  }
  console.log('ms por nota (aprox):', tiempos.join(' '));
  await est.waitForTimeout(600);
  await est.screenshot({ path: OUT + '/8-completada.png', fullPage: true });
  await est.fill('textarea[name="comentario"]', 'El Sol sostenido me costó un poco.');
  await est.click('#btn-entregar'); await est.waitForTimeout(1200);
  await est.locator('#resultado-card').screenshot({ path: OUT + '/9-entregada.png' });

  // metrónomo
  await est.click('#metro-iniciar'); await est.selectOption('#metro-compas', '3/4'); await est.waitForTimeout(1300);
  await est.locator('#metro-card').screenshot({ path: OUT + '/10-metronomo.png' });
  await est.click('#metro-iniciar');

  // ---- profesor ve el resultado
  await prof.reload(); await prof.goto(BASE + '/dashboard-profesor#tareas'); await prof.waitForTimeout(500);
  await prof.locator('#tareas-tbody [data-entregas]').first().click(); await prof.waitForTimeout(900);
  await prof.locator('#modal-entregas').screenshot({ path: OUT + '/11-profesor-entrega.png' });

  // ---- director
  const dir = await nuevo();
  await login(dir, 'director@msea.test', 'director123', 'admin');
  await dir.goto(BASE + '/dashboard-admin/biblioteca'); await dir.waitForTimeout(500);
  await dir.screenshot({ path: OUT + '/12-director-biblioteca.png', fullPage: true });

  // ---- móvil y vista previa con ritmo propio
  const mov = await nuevo(390, 844);
  await login(mov, 'profesor@msea.test', 'profesor123', 'profesor');
  const cfg = { octavas: 2, direccion: 'subir_bajar', ritmo: { modo: 'patron', celdas: [['negra_puntillo', 'corchea'], ['corchea', 'semicorchea', 'semicorchea'], ['semicorchea','semicorchea','semicorchea','semicorchea','corchea_puntillo','semicorchea']] }, bpm: 72, compas: '6/8', tolerancia: 10, digitacion: true };
  await mov.goto(BASE + '/biblioteca/escalas/1/probar?config=' + encodeURIComponent(JSON.stringify(cfg))); await mov.waitForTimeout(900);
  for (let i = 0; i < 2; i++) await mov.click('#btn-simular');
  await mov.waitForTimeout(400);
  await mov.screenshot({ path: OUT + '/13-movil-vista-previa.png', fullPage: true });
  const ancho = await mov.evaluate(() => document.documentElement.scrollWidth);
  console.log('scrollWidth móvil:', ancho);
  // desktop preview 3 octavas + escala completa
  const pre = await nuevo();
  await login(pre, 'profesor@msea.test', 'profesor123', 'profesor');
  await pre.goto(BASE + '/biblioteca/escalas/1/probar?config=' + encodeURIComponent(JSON.stringify({ ...cfg, octavas: 3, ritmo: { modo: 'uniforme', figura: 'semicorchea', repeticiones: 16 } }))); await pre.waitForTimeout(900);
  for (let i = 0; i < 3; i++) await pre.click('#btn-simular');
  await pre.check('#ver-completa'); await pre.waitForTimeout(300);
  await pre.screenshot({ path: OUT + '/14-tres-octavas.png', fullPage: true });

  console.log('errores:', errs.length ? errs.join('\n') : 'ninguno');
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
