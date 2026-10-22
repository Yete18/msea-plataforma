/*
 * Recorridos completos por rol (fase F), con la CSP real del sitio:
 * profesor asigna -> estudiante entrega con audio -> profesor califica ->
 * familia ve la nota -> dirección gestiona -> estudiante ve nota y comunicado.
 * Requiere los datos de demostración (php artisan migrate:fresh --seed) y un
 * archivo WAV corto (por defecto tests/fixtures/prueba.wav).
 *
 * Uso:  BASE=http://127.0.0.1:8000 NODE_PATH=$(npm root -g) node tests/e2e/recorridos-por-rol.cjs
 * Cambia datos: úsalo solo contra una base de prueba, nunca contra producción.
 */
const { chromium } = require('playwright');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const WAV = process.env.WAV || path.join(__dirname, '..', 'fixtures', 'prueba.wav');
const TITULO = 'Recorrido F ' + Date.now().toString(36);
let fallos = 0;
const ok = (c, m) => { if (!c) fallos++; console.log(c ? 'OK   ' : 'FALLA', m); };

async function sesion(b, correo, clave, ancho = 1366) {
  const ctx = await b.newContext({ viewport: { width: ancho, height: 900 }, acceptDownloads: true });
  const p = await ctx.newPage();
  const problemas = [];
  p.on('pageerror', (e) => problemas.push('JS ' + String(e).slice(0, 140)));
  p.on('console', (m) => m.type() === 'error' && problemas.push('consola ' + m.text().slice(0, 140)));
  p.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('favicon')) problemas.push(r.status() + ' ' + r.url().replace(BASE, '')); });
  await p.addInitScript(() => document.addEventListener('securitypolicyviolation', (e) => console.error('CSP ' + e.violatedDirective)));
  await p.goto(BASE + '/login');
  await p.fill('#correo', correo); await p.fill('#contrasena', clave);
  await Promise.all([p.waitForNavigation(), p.click('#btn-login')]);
  return { ctx, p, problemas };
}
async function salir(s, rol) {
  await Promise.all([s.p.waitForNavigation(), s.p.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
  ok(s.p.url().endsWith('/login'), `${rol}: cerrar sesión vuelve al login`);
  ok(!s.problemas.length, `${rol}: sin errores JS, CSP ni respuestas 4xx/5xx ${s.problemas.slice(0, 4).join(' | ')}`);
  await s.ctx.close();
}
const toast = (p) => p.waitForSelector('.toast.show, #toast.show', { timeout: 5000 }).then((t) => t.textContent()).catch(() => '');
const cerrarBienvenida = async (p) => { await p.waitForTimeout(1200); if (await p.$('.msea-modal-ok')) await p.click('.msea-modal-ok'); };

(async () => {
  const b = await chromium.launch();

  // 1. Profesor asigna una tarea.
  let s = await sesion(b, 'profesor@msea.test', 'profesor123');
  ok(s.p.url().includes('/dashboard-profesor'), 'profesor: entra a su panel sin elegir rol');
  await s.p.click('#btn-nueva-tarea');
  await s.p.fill('#form-tarea [name="titulo"]', TITULO);
  const manana = new Date(Date.now() + 86400000 * 3).toISOString().slice(0, 10);
  await s.p.fill('#tarea-fecha', manana);
  await s.p.fill('#form-tarea [name="descripcion"]', 'Grabar la escala dos veces, a 60 pulsos.');
  await s.p.click('#btn-guardar-tarea');
  const t1 = await toast(s.p);
  ok(/tarea/i.test(t1), 'profesor: crea la tarea [' + t1.trim() + ']');
  await s.p.goto(BASE + '/dashboard-profesor#tareas'); await s.p.reload(); await s.p.waitForLoadState('networkidle');
  ok(await s.p.locator('#page-tareas', { hasText: TITULO }).count() > 0, 'profesor: la tarea aparece en su lista');
  await salir(s, 'profesor');

  // 2. Estudiante entrega con audio, escribe al profesor y actualiza su celular (390 px).
  s = await sesion(b, 'estudiante@msea.test', 'estudiante123', 390);
  ok(s.p.url().includes('/dashboard-estudiante'), 'estudiante: entra a su panel');
  await cerrarBienvenida(s.p);
  await s.p.goto(BASE + '/dashboard-estudiante#tareas'); await s.p.reload(); await s.p.waitForLoadState('networkidle'); await cerrarBienvenida(s.p);
  const tarjeta = s.p.locator('#view-tareas .tarea-card-full', { hasText: TITULO });
  ok(await tarjeta.count() === 1, 'estudiante: ve la tarea nueva');
  await tarjeta.locator('.btn-entregar').click();
  await s.p.setInputFiles('#entrega-archivo', WAV);
  await s.p.fill('#form-entrega textarea', 'La grabé a 60 pulsos.').catch(() => {});
  await s.p.click('#btn-entrega-submit');
  ok(/entreg/i.test(await toast(s.p)), 'estudiante: entrega con grabación');
  await s.p.waitForTimeout(500);
  ok(await s.p.locator('#modal-entrega.hidden').count() === 1, 'estudiante: el diálogo se cierra al entregar');
  await s.p.goto(BASE + '/dashboard-estudiante#mensajes'); await s.p.reload(); await s.p.waitForLoadState('networkidle'); await cerrarBienvenida(s.p);
  await s.p.fill('#form-mensaje textarea[name="contenido"]', 'Profe, ya entregué la escala.');
  await s.p.click('#form-mensaje button[type="submit"]');
  ok(/enviad|mensaje/i.test(await toast(s.p)), 'estudiante: envía un mensaje al profesor');
  await s.p.goto(BASE + '/dashboard-estudiante#perfil'); await s.p.reload(); await s.p.waitForLoadState('networkidle'); await cerrarBienvenida(s.p);
  await s.p.click('#btn-edit-info');
  ok(await s.p.locator('#edit-ci, [name="ci"]').count() === 0, 'estudiante: no puede editar el CI');
  await s.p.fill('[name="celular"]', '71234567');
  await s.p.click('#btn-save-info');
  ok(/actualiz|guard/i.test(await toast(s.p)), 'estudiante: guarda su celular');
  await salir(s, 'estudiante');

  // 3. Profesor califica la entrega.
  s = await sesion(b, 'profesor@msea.test', 'profesor123');
  await s.p.goto(BASE + '/dashboard-profesor#tareas'); await s.p.reload(); await s.p.waitForLoadState('networkidle');
  const fila = s.p.locator('#page-tareas tr, #page-tareas .tarea-fila, #page-tareas article', { hasText: TITULO }).first();
  await fila.locator('[data-entregas]').click();
  await s.p.waitForSelector('.entrega-detalle');
  ok(await s.p.locator('.entrega-detalle audio').count() > 0, 'profesor: escucha la grabación');
  await s.p.fill('.entrega-detalle [name="calificacion"]', '90');
  await s.p.fill('.entrega-detalle [name="comentario_profesor"]', 'Muy buena afinación.');
  await s.p.click('.entrega-detalle [data-accion="calificar"]');
  ok(/calific|XP/i.test(await toast(s.p)), 'profesor: califica con 90');
  await s.p.keyboard.press('Escape');
  await s.p.goto(BASE + '/dashboard-profesor#mensajes'); await s.p.reload(); await s.p.waitForLoadState('networkidle');
  ok(await s.p.locator('#page-mensajes', { hasText: 'ya entregué la escala' }).count() > 0, 'profesor: recibe el mensaje del estudiante');
  await salir(s, 'profesor (calificar)');

  // 4. Familia ve la nota y escribe al profesor (768 px).
  s = await sesion(b, 'padre@msea.test', 'padre123', 768);
  ok(s.p.url().includes('/dashboard-padre'), 'familia: entra a su panel');
  const texto = await s.p.textContent('body');
  ok(texto.includes(TITULO) && /90/.test(texto), 'familia: ve la tarea calificada con 90');
  await s.p.fill('.mensaje-form textarea[name="contenido"]', 'Gracias, profesor.');
  await Promise.all([s.p.waitForNavigation(), s.p.click('.mensaje-form button[type="submit"]')]);
  ok(await s.p.locator('.alerta--exito').count() > 0, 'familia: envía un mensaje');
  await salir(s, 'familia');

  // 5. Dirección: alta de profesor, corrección de datos, comunicado, auditoría y reporte.
  s = await sesion(b, 'director@msea.test', 'director123');
  ok(s.p.url().includes('/dashboard-admin'), 'dirección: entra al centralizador');
  await s.p.goto(BASE + '/dashboard-admin/profesores');
  const nuevo = s.p.locator('form[action$="/dashboard-admin/profesores"]');
  await nuevo.locator('[name="nombres"]').fill('Lucía');
  await nuevo.locator('[name="apellido_paterno"]').fill('Recorrido');
  await nuevo.locator('[name="correo"]').fill(`lucia.${Date.now()}@msea.test`);
  await nuevo.locator('[name="contrasena"]').fill('Clave2026segura');
  await nuevo.locator('[name="contrasena_confirmation"]').fill('Clave2026segura');
  await Promise.all([s.p.waitForNavigation(), nuevo.locator('button[type="submit"]').click()]);
  ok(await s.p.locator('.alerta--exito').count() > 0, 'dirección: registra una profesora');
  await s.p.goto(BASE + '/dashboard-admin/usuarios');
  const editor = s.p.locator('details.inline-editor').first();
  await editor.locator('summary').click();
  await editor.locator('[name="ci"]').fill('7654321');
  await editor.locator('[name="direccion"]').fill('Calle Recorrido 12');
  await Promise.all([s.p.waitForNavigation(), editor.locator('button[type="submit"]').first().click()]);
  ok(await s.p.locator('.alerta--exito').count() > 0, 'dirección: corrige CI y dirección de un estudiante');
  await s.p.goto(BASE + '/dashboard-admin/comunicados');
  await s.p.fill('[name="titulo"]', 'Ensayo general');
  await s.p.fill('[name="mensaje"]', 'El sábado hay ensayo general a las 9:00.');
  await Promise.all([s.p.waitForNavigation(), s.p.click('form[action$="/comunicados"] button[type="submit"]')]);
  ok(await s.p.locator('.alerta--exito').count() > 0, 'dirección: envía un comunicado');
  await s.p.goto(BASE + '/dashboard-admin/auditoria');
  ok(/login|sesi/i.test(await s.p.textContent('main')), 'dirección: la auditoría registra los inicios de sesión');
  await s.p.goto(BASE + '/dashboard-admin');
  const enlace = s.p.locator('a.reporte').first();
  const [descarga] = await Promise.all([s.p.waitForEvent('download'), enlace.click()]);
  ok(/\.csv$/.test(descarga.suggestedFilename()), 'dirección: descarga un reporte CSV ' + descarga.suggestedFilename());
  await salir(s, 'dirección');

  // 6. El estudiante ve la nota y el comunicado.
  s = await sesion(b, 'estudiante@msea.test', 'estudiante123');
  await cerrarBienvenida(s.p);
  await s.p.goto(BASE + '/dashboard-estudiante#tareas'); await s.p.reload(); await s.p.waitForLoadState('networkidle'); await cerrarBienvenida(s.p);
  ok(await s.p.locator('#view-tareas .tarea-card-full', { hasText: TITULO }).filter({ hasText: '90' }).count() === 1, 'estudiante: ve su nota de 90');
  await s.p.click('#notif-btn');
  ok(await s.p.locator('#notif-panel', { hasText: 'Ensayo general' }).count() > 0, 'estudiante: recibe el comunicado');
  await s.p.keyboard.press('Escape');
  await salir(s, 'estudiante (resultado)');

  console.log(fallos ? `\n${fallos} FALLA(S)` : '\nTodo correcto');
  await b.close();
})().catch((e) => { console.error('ERROR', e.message.split('\n')[0]); process.exit(1); });
