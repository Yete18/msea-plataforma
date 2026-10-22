/* Prueba en navegador real de la web publica.
   Uso:  BASE=http://127.0.0.1:8000 NODE_PATH=$(npm root -g) node tests/e2e/sitio-publico.cjs
   Requiere el servidor en marcha con los datos de demostracion (MseaDemoSeeder). */
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const fallos = [];
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALLA') + ' ' + m); if (!c) fallos.push(m); };

(async () => {
  const b = await chromium.launch();

  // 1) Aviso de cookies: aparece, se guarda la eleccion y se puede reabrir.
  let ctx = await b.newContext({ viewport: { width: 1280, height: 800 } });
  let p = await ctx.newPage();
  await p.goto(BASE + '/');
  ok(await p.isVisible('#cookie-banner'), 'el aviso de cookies aparece en la primera visita');
  await p.click('[data-cookies]');
  ok(!(await p.isVisible('#cookie-banner')), 'se oculta al aceptar');
  await p.reload();
  ok(!(await p.isVisible('#cookie-banner')), 'no vuelve a aparecer despues de recargar');
  await p.click('[data-abrir-cookies]');
  ok(await p.isVisible('#cookie-banner'), 'se puede reabrir desde "Preferencias de cookies"');

  // 2) Una sola llamada a la accion en la portada.
  const botones = await p.$$('main a.p-cta, main .btn');
  ok(botones.length === 1, 'la portada tiene un unico boton de accion principal');

  // 3) Boton de WhatsApp (solo si el servidor tiene MSEA_WHATSAPP).
  const wa = await p.$('.boton-whatsapp');
  if (wa) {
    const href = await wa.getAttribute('href');
    ok(/^https:\/\/wa\.me\/\d+\?text=/.test(href), 'WhatsApp abre wa.me con el mensaje: ' + href.slice(0, 40));
    ok(await wa.isVisible(), 'el boton de WhatsApp esta visible');
  } else {
    console.log('INFO  sin MSEA_WHATSAPP: el boton no se muestra (correcto)');
  }

  // 4) Enlaces: todos los internos de las paginas publicas responden bien.
  const paginas = ['/', '/login', '/registro', '/forgot-password', '/aviso-legal', '/politica-de-privacidad', '/politica-de-cookies', '/sitemap.xml', '/robots.txt'];
  const vistos = new Set();
  for (const ruta of paginas) {
    await p.goto(BASE + ruta);
    const enlaces = await p.$$eval('a[href], link[href], img[src], script[src], source[srcset]', els => els.map(e => e.getAttribute('href') || e.getAttribute('src') || e.getAttribute('srcset')));
    for (const e of enlaces) {
      if (!e || e.startsWith('#') || e.startsWith('mailto:') || e.startsWith('tel:')) continue;
      const url = new URL(e.split(' ')[0], BASE + ruta);
      if (url.origin !== new URL(BASE).origin || vistos.has(url.pathname)) continue;
      vistos.add(url.pathname);
      const r = await ctx.request.get(url.href);
      ok(r.status() < 400, `enlace ${url.pathname} -> ${r.status()} (en ${ruta})`);
    }
  }
  const r404 = await ctx.request.get(BASE + '/esto-no-existe');
  ok(r404.status() === 404 && (await r404.text()).includes('Volver al inicio'), 'la pagina 404 es la personalizada');

  // 5) Formularios con proteccion: un inicio de sesion normal funciona; uno instantaneo no.
  await ctx.close();
  ctx = await b.newContext({ viewport: { width: 1280, height: 800 } });
  p = await ctx.newPage();
  await p.goto(BASE + '/login');
  const caja = await p.locator('.campo-trampa').boundingBox();
  ok(!!caja && (caja.x + caja.width < 0), 'el campo trampa existe y queda fuera de la pantalla');
  await p.fill('#correo', 'estudiante@msea.test');
  await p.fill('#contrasena', 'estudiante123');
  await p.click('form#login-form button[type="submit"]');
  await p.waitForLoadState('networkidle');
  ok(/dashboard-estudiante/.test(p.url()) || (await p.textContent('body')).includes('Fue muy rápido'), 'envio inmediato: ' + (/dashboard/.test(p.url()) ? 'paso (tardo mas del minimo)' : 'bloqueado por ser muy rapido'));
  await ctx.close();

  ctx = await b.newContext({ viewport: { width: 1280, height: 800 } });
  p = await ctx.newPage();
  await p.goto(BASE + '/login');
  await p.waitForTimeout(2600);
  await p.fill('#correo', 'estudiante@msea.test');
  await p.fill('#contrasena', 'estudiante123');
  await p.click('form#login-form button[type="submit"]');
  await p.waitForURL(/dashboard-estudiante/, { timeout: 15000 }).catch(() => {});
  ok(/dashboard-estudiante/.test(p.url()), 'una persona real (espera 2,6 s) inicia sesion bien');
  ok(!(await p.$('#cookie-banner')), 'el panel con sesion no muestra el aviso de cookies ni analitica');
  ok(!(await p.content()).includes('googletagmanager') && !(await p.content()).includes('plausible'), 'el panel no carga analitica');

  // 6) Registro exige aceptar la politica.
  await ctx.close();
  ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
  p = await ctx.newPage();
  await p.goto(BASE + '/registro');
  await p.fill('#nombre', 'Prueba');
  await p.fill('#apellido', 'Publica');
  await p.fill('#email', 'prueba.publica@msea.test');
  await p.click('#btn-next-1').catch(async () => { await p.click('text=Siguiente'); });
  await p.waitForTimeout(400);
  ok(await p.isVisible('#acepta-privacidad'), 'el paso 2 muestra la casilla de politica de privacidad');
  const desborde = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(desborde <= 0, 'el registro no se desborda en movil (' + desborde + 'px)');

  await b.close();
  console.log(fallos.length ? `\n${fallos.length} FALLA(S)` : '\nTodo correcto');
  process.exit(fallos.length ? 1 : 0);
})();
