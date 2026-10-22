const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const OUT = process.env.OUT || 'storage/app/e2e';
(async () => {
  const b = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] });
  const errs = [];
  const nuevo = async (w = 1366, h = 900) => { const p = await (await b.newContext({ viewport: { width: w, height: h } })).newPage(); p.on('pageerror', e => errs.push(String(e))); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); }); return p; };
  const login = async (p, c, k, r) => { await p.goto(BASE + '/login'); await p.fill('input[name="correo"]', c); await p.fill('input[name="contrasena"]', k); /* UX-07: el rol ya no se elige al iniciar sesión. */ await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]); };

  const dir = await nuevo();
  await login(dir, 'director@msea.test', 'director123', 'admin');
  await dir.goto(BASE + '/dashboard-admin/digitalizador');
  await dir.screenshot({ path: OUT + '/1-subir.png', fullPage: true });
  await dir.setInputFiles('input[name="pdf"]', process.env.PDF || 'tests/e2e/metodo-prueba.pdf');
  await dir.fill('input[name="titulo"]', 'Método de prueba MSEA');
  await dir.fill('input[name="metodo"]', 'Método de prueba');
  await dir.fill('input[name="paginas"]', '4, 6');
  await dir.check('input[name="derechos"]');
  const t0 = Date.now();
  await Promise.all([dir.waitForNavigation({ timeout: 300000 }), dir.click('form[enctype] button[type="submit"]')]);
  console.log('procesado en', ((Date.now() - t0) / 1000).toFixed(1), 's ->', dir.url());
  await dir.waitForTimeout(800);
  await dir.screenshot({ path: OUT + '/2-revision.png', fullPage: false });
  await dir.locator('#pagina-4').screenshot({ path: OUT + '/3-pagina4.png' });
  const resumenes = await dir.locator('[data-resumen]').allInnerTexts();
  console.log('resumenes:', resumenes.join(' || '));
  // Corregir el ejercicio 1 de la página 4 con el texto correcto y publicar
  const form = dir.locator('form.ejercicio-revision').first();
  const correcto = Array(4).fill('Si4:c/1 Do#5:c/2 Si4:c Do#5:c').join(' ') + ' ' + Array(4).fill('Do#5:c/2 Re5:c/3 Do#5:c Re5:c').join(' ');
  await form.locator('textarea').fill(correcto);
  await form.locator('input[name="nombre"]').fill('Método de prueba · pág. 4 · n.º 1 (trinos 1-2 y 2-3)');
  await dir.waitForTimeout(500);
  await form.screenshot({ path: OUT + '/4-corregido.png' });
  await Promise.all([dir.waitForNavigation(), form.locator('button[value="publicar"]').click()]);
  await dir.waitForTimeout(500);
  console.log('alerta:', await dir.locator('.alert').first().innerText().catch(() => '-'));
  const idPub = await dir.locator('a:has-text("Probar")').first().getAttribute('href');
  console.log('probar:', idPub);

  // Profesor: biblioteca de métodos y técnica
  const prof = await nuevo();
  await login(prof, 'profesor@msea.test', 'profesor123', 'profesor');
  await prof.goto(BASE + '/dashboard-profesor#biblioteca'); await prof.waitForTimeout(400);
  await prof.click('[data-categoria="metodo"]'); await prof.waitForTimeout(300);
  await prof.screenshot({ path: OUT + '/5-prof-metodos.png' });
  await prof.click('[data-categoria="tecnica_mano_izquierda"]'); await prof.waitForTimeout(300);
  await prof.screenshot({ path: OUT + '/6-prof-tecnica.png' });
  await prof.locator('[data-asignar-tecnica]').nth(1).click(); await prof.waitForTimeout(500);
  await prof.locator('#tecnica-config').screenshot({ path: OUT + '/7-modal-tecnica.png' });
  await prof.fill('#tec-bpm', '72'); await prof.fill('#tec-objetivo', '100');
  await Promise.all([prof.waitForNavigation(), prof.click('#btn-guardar-tarea')]);
  console.log('tarea:', await prof.locator('#tareas-tbody tr').first().innerText());

  // Estudiante: practica en modo velocidad con micrófono simulado
  const est = await nuevo();
  await est.addInitScript(() => {
    navigator.mediaDevices.getUserMedia = async () => { const ctx = new AudioContext(); const osc = ctx.createOscillator(); osc.type = 'sawtooth'; const g = ctx.createGain(); g.gain.value = 0.4; const d = ctx.createMediaStreamDestination(); osc.connect(g).connect(d); osc.start(); window.__mic = { osc, g }; return d.stream; };
  });
  await login(est, 'estudiante@msea.test', 'estudiante123', 'estudiante');
  await est.goto(BASE + '/dashboard-estudiante#tareas'); await est.waitForTimeout(500);
  const href = await est.locator('a:has-text("Realizar tarea")').first().getAttribute('href');
  await est.goto(BASE + href.replace(/^https?:\/\/[^/]+/, '')); await est.waitForTimeout(700);
  await est.click('#btn-microfono'); await est.waitForTimeout(300);
  const total = Number(await est.locator('#paso-total').innerText());
  const t1 = Date.now();
  for (let i = 0; i < total; i++) {
    const hz = Number(await est.locator('#objetivo-hz').innerText());
    await est.evaluate((f) => { window.__mic.osc.frequency.value = f * Math.pow(2, 2 / 1200); }, hz);
    if (i === 6) { await est.waitForTimeout(60); await est.screenshot({ path: OUT + '/8-velocidad.png', fullPage: true }); }
    if (i < total - 1) await est.waitForFunction((n) => document.getElementById('paso-actual').textContent === `Nota ${n}`, i + 2, { timeout: 4000 });
    else await est.waitForSelector('#resultado-card:not(.hidden)', { timeout: 4000 });
  }
  console.log(`${total} notas en ${((Date.now() - t1) / 1000).toFixed(1)} s`);
  await est.waitForTimeout(600);
  await est.locator('#resultado-card').screenshot({ path: OUT + '/9-resultado-velocidad.png' });
  console.log('velocidad:', await est.locator('#velocidad-resultado').innerText());
  console.log('errores:', errs.join('\n') || 'ninguno');
  await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
