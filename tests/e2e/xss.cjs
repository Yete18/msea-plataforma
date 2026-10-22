/*
 * Prueba E2E de XSS almacenado (opcional, requiere Node + Playwright).
 * 1) php artisan migrate:fresh --seed  (base de datos de PRUEBA, nunca la real)
 * 2) php artisan tinker < tests/e2e/xss-datos.php   (crea datos con cargas XSS)
 * 3) php artisan serve --port=8123
 * 4) MSEA_URL=http://127.0.0.1:8123 node tests/e2e/xss.cjs
 * Se ejecuta con bypassCSP para comprobar el ESCAPE por si solo.
 */
const { chromium } = require('playwright');
const BASE = process.env.MSEA_URL || 'http://127.0.0.1:8123';
(async () => {
  const b = await chromium.launch(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {});
  const resultados = [];
  for (const [correo, clave, rol, urls] of [
    ['estudiante@msea.test', 'estudiante123', 'estudiante', ['/dashboard-estudiante', '/dashboard-estudiante#ranking']],
    ['profesor@msea.test', 'profesor123', 'profesor', ['/dashboard-profesor', '/dashboard-profesor#alumnos', '/dashboard-profesor#tareas']],
    ['director@msea.test', 'director123', 'admin', ['/dashboard-admin', '/dashboard-admin/usuarios']],
  ]) {
    // bypassCSP: se prueba el ESCAPE por si solo, sin la ayuda de la CSP.
    const ctx = await b.newContext({ bypassCSP: true });
    const p = await ctx.newPage();
    await p.goto(`${BASE}/login`);
    await p.fill('input[name="correo"]', correo); await p.fill('input[name="contrasena"]', clave);
    /* UX-07: el rol ya no se elige al iniciar sesión. */
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);
    for (const u of urls) {
      await p.goto(BASE + u, { waitUntil: 'networkidle' });
      await p.evaluate(() => window.dispatchEvent(new HashChangeEvent('hashchange')));
      if (u.endsWith('#tareas') && rol === 'profesor') {
        const btn = await p.$('#page-tareas [data-entregas]:not([disabled])');
        if (btn) { await btn.click(); await p.waitForTimeout(600); }
      }
      await p.hover('body');
      await p.waitForTimeout(400);
      const r = await p.evaluate(() => ({ xss: window.__xss || null, imgs: document.querySelectorAll('img[src="x"]').length, svgOnload: document.querySelectorAll('svg[onload]').length, scripts: [...document.scripts].filter(s => s.textContent.includes('__xss')).length, visible: document.body.innerText.includes('<img src=x') }));
      resultados.push(`${rol} ${u}: ejecutado=${r.xss} imgs_inyectadas=${r.imgs} svg_onload=${r.svgOnload} scripts=${r.scripts} texto_visible_escapado=${r.visible}`);
    }
    await ctx.close();
  }
  await b.close();
  console.log(resultados.join('\n'));
  const fallos = resultados.filter((r) => !r.includes('ejecutado=null') || !r.includes('imgs_inyectadas=0') || !r.includes('svg_onload=0'));
  if (fallos.length) { console.error('XSS DETECTADO'); process.exit(1); }
  console.log('OK: ninguna carga XSS se ejecuto.');
})();
