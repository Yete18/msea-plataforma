# Pruebas de MSEA

## Suite PHP (PHPUnit)

```bash
php artisan test                         # SQLite en memoria (phpunit.xml)
vendor/bin/phpunit -c phpunit.pgsql.xml  # PostgreSQL (base separada msea_testing)
```

Antes de ejecutar las pruebas sobre PostgreSQL hay que crear una base y un usuario **exclusivos para pruebas**. Se usa `RefreshDatabase`, que borra todas las tablas:

```sql
CREATE ROLE msea LOGIN PASSWORD 'msea';
CREATE DATABASE msea_testing OWNER msea;
```

**Nunca** apuntes `phpunit.pgsql.xml` ni `.env.testing` a la base de desarrollo o de producción.

Atajos de Composer: `composer test`, `composer test:pgsql`, `composer lint` (Pint en modo comprobación) y `composer format` (aplica Pint). El estilo es el preset `laravel` con `!$x` sin espacio (`pint.json`).

### Integración continua

`.github/workflows/ci.yml` se ejecuta en cada push a `main` y en cada pull request:

| Trabajo | Qué hace |
|---|---|
| Estilo (Pint) | `pint --test`; falla si algún archivo no sigue `pint.json` |
| Pruebas (SQLite) | `php artisan test` con PHP 8.2 y 8.3 |
| Pruebas (PostgreSQL 16) | `phpunit -c phpunit.pgsql.xml` contra un servicio `postgres:16` con la base `msea_testing` |
| Auditoría de dependencias | `composer audit --locked`; informativo (no bloquea) hasta actualizar las dependencias con avisos |

### Último resultado registrado (17/10/2026, auditoría integral, fases A a F)

| Motor | Resultado |
|---|---|
| SQLite (en memoria) | 211 pruebas: 209 correctas y 2 omitidas (solo aplican a PostgreSQL), 1326 aserciones |
| PostgreSQL 16 (`msea_testing`) | 211 pruebas, 1329 aserciones, todas correctas |
| PostgreSQL 16: actualización simulada sobre datos antiguos (contraseña en texto plano y estados inválidos) | Correcto: las contraseñas quedan con hash y las restricciones se crean `NOT VALID` con aviso en el log |
| PostgreSQL 16: instalación limpia en modo producción | Correcto: 0 usuarios, catálogos y 92 ejercicios de técnica |

### Resultado anterior (10/10/2026)

| Motor | Resultado |
|---|---|
| SQLite (en memoria) | 150 pruebas, 968 aserciones, todas correctas |
| PostgreSQL 16 (`msea_testing`) | 150 pruebas, 968 aserciones, todas correctas |
| PostgreSQL 16: `migrate:rollback --step=1` y `migrate` de `2026_10_13_000001_msea_biblioteca_escalas` y de `2026_10_14_000001_msea_escalas_mayores_menores` y `2026_10_15_000001_msea_escalas_viola_chelo_contrabajo` (48 escalas por instrumento) | Correcto |
| PostgreSQL 16: `migrate:rollback --step=1` y `migrate` de `2026_10_16_000001_msea_tecnica_y_digitalizador` | Correcto |
| PostgreSQL 16: `migrate:rollback --step=1` y `migrate` de `2026_10_11_000001_msea_habitos_estudiante` | Correcto |
| PostgreSQL 16: `migrate:fresh`, luego `db:seed --class=MseaDemoSeeder`, luego `migrate:rollback --step=1` y de nuevo `migrate` | Correcto. Los datos se conservan y el saldo inicial de XP se vuelve a cargar. |

### Qué cubre cada grupo

| Archivo | Cobertura |
|---|---|
| `Feature/Seguridad/TareasAjenasTest` | IDOR en tareas, entregas, material y grabaciones. Archivos con MIME falso. Descarga autorizada. |
| `Feature/Seguridad/AutenticacionTest` | Login con el hash, mensaje único, límites de intentos, cuenta desactivada, cambio de rol y huella de credencial. |
| `Feature/Seguridad/ApiMovilTest` | Token con hash, caducidad, revocación, máximo de sesiones, límites de intentos y práctica medida por el servidor. |
| `Feature/Seguridad/RecuperacionYCorreoTest` | Correo en mayúsculas, intentos por código, Mailable y revocación al restablecer. |
| `Feature/Seguridad/CsvYCabecerasTest` | Inyección de fórmulas en CSV, CSP con nonce y cabeceras. |
| `Feature/Gamificacion/XpTest` | Idempotencia del libro de XP, tope diario, duración mínima, ranking y rachas. |
| `Feature/Gamificacion/TeoriaYCursosTest` | Corrección en el servidor, respuesta oculta, lecciones publicadas y progreso. |
| `Feature/GestionDirectorTest` | Activar y desactivar cuentas, cambiar roles, catálogos, cursos y comunicados. |
| `Feature/Seguridad/FaseAIntegridadTest` | Fase A: integridad de tareas y entregas, archivos privados, reversión segura y datos de menores. |
| `Feature/Seguridad/FaseBDatosTest` | Fase B: catálogos idempotentes, contraseñas antiguas con hash, restricciones, práctica concurrente, cola del digitalizador y valores seguros de producción. |
| `Feature/Seguridad/FaseDAcademicoTest` | Fase D: inicio de sesión sin rol (y rol antiguo ignorado), CI, dirección y fecha de nacimiento solo por la dirección, XP de teoría tras un error y racha con día de descanso. |
| `Feature/Seguridad/FaseEAccesibilidadTest` | Fase E: CSP sin estilos en línea, ninguna página con atributos `style`, sprite externo con todos los iconos usados, tema aplicado en el `<head>` con nonce y errores del servidor asociados a su campo. |
| `Feature/Seguridad/GoogleLoginTest` | Login con Google en web y API: vinculación, cuenta inexistente, token de otra app, sin verificar o vencido, cuenta desactivada y CSP (Google simulado con `Http::fake`). |
| `Feature/ExperienciaYSesionTest` | Error 419 (cerrar sesión con página vieja, formularios y JSON), redirección del login con sesión abierta, `no-store`, `/csrf-token`, meta diaria, racha vigente, atención del profesor, resumen familiar, pulso del director y recursos locales. |
| `Feature/BibliotecaEscalasTest` | Escalas con frecuencias exactas (La4 = 440 Hz), ortografía y armadura, ritmos, tarea de escala del profesor, práctica y entrega del estudiante, permisos por rol y gestión del director. |
| `Feature/TecnicaYDigitalizadorTest` | Ejercicios de dedos por instrumento, modo velocidad, notación de texto, lectura de MusicXML, páginas elegidas, digitalización sin OMR y con un motor simulado (solo se piden las páginas indicadas) y publicación en la biblioteca. |
| Resto de `Feature/` y `Unit/` | Flujos previos: login, registro, paneles, recuperación y fotos. |

## Prueba E2E de XSS (navegador)

Requiere Node y Playwright con Chromium.

```bash
php artisan migrate:fresh --seed --seeder=MseaDemoSeeder   # en una BD desechable
php artisan tinker --execute="$(grep -v '^<?php' tests/e2e/xss-datos.php)"   # inserta cargas maliciosas
php artisan serve --port=8123 &
MSEA_URL=http://127.0.0.1:8123 NODE_PATH=$(npm root -g) node tests/e2e/xss.cjs
```

El script entra con cada rol, recorre todas las secciones y falla si alguna carga ejecuta código. Última ejecución (17/10/2026): ninguna se ejecutó y el texto aparece escapado.

## Recorridos completos por rol (navegador)

`tests/e2e/recorridos-por-rol.cjs` usa la CSP real del sitio y falla ante cualquier error de JavaScript, violación de la CSP o respuesta 4xx/5xx. Recorre: el profesor asigna una tarea, el estudiante (a 390 px) entrega con una grabación, escribe al profesor y cambia su celular (sin poder tocar su CI), el profesor escucha y califica, la familia (a 768 px) ve la nota y escribe, la dirección registra una profesora, corrige datos de un estudiante, envía un comunicado, revisa la auditoría y descarga un CSV, y el estudiante ve su nota y el comunicado.

```bash
php artisan migrate:fresh --seed --seeder=MseaDemoSeeder   # en una BD desechable
php artisan serve --port=8000 &
BASE=http://127.0.0.1:8000 NODE_PATH=$(npm root -g) node tests/e2e/recorridos-por-rol.cjs
```

Última ejecución (17/10/2026): 36 de 36 comprobaciones correctas. `escala.cjs`, `digitalizador.cjs` y `sitio-publico.cjs` también pasaron ese día.

## Accesibilidad

Se revisó con `axe-core` (WCAG 2 A y AA) cada sección de los cuatro paneles, las páginas de acceso y las públicas, a 1366, 768 y 390 px, en tema claro y oscuro, el 17/10/2026: sin infracciones y sin desborde horizontal. Además se midió que todo control táctil tenga al menos 44 × 44 px y se comprobó con teclado que los diálogos atrapan el foco, se cierran con Escape y devuelven el foco, y que cambiar de sección mueve el foco al título. No se probó con un lector de pantalla real (NVDA o VoiceOver).

## App Flutter

```bash
cd mobile_flutter && flutter test
```

`test/msea_api_test.dart` comprueba el cliente HTTP con `MockClient`: guardado del token, 401, credenciales incorrectas, 500 sin detalles y flujo de práctica.

## Comprobaciones manuales recomendadas antes de publicar

1. Envío real del correo de recuperación con el SMTP de producción (hoy está desactivado: no hay SMTP).
2. Subida de una grabación desde un teléfono (formatos m4a, ogg y webm) y escucha por parte del profesor.
3. Compilación de la app en un dispositivo Android real contra el servidor HTTPS (MOV-01).
4. Recorrido con un lector de pantalla (NVDA en Windows o TalkBack en Android).
5. Botón de Google con un `GOOGLE_CLIENT_ID` real, ya que la CSP no permite estilos en línea.

## Prueba E2E de la práctica de escalas

Con el servidor de demostración, `tests/e2e/escala.cjs` (Playwright) reemplaza el micrófono por un oscilador (onda diente de sierra) y toca cada nota objetivo. Comprueba que una nota desafinada (+30 cents) o una octava equivocada no avanzan y que cada nota afinada avanza tras sostenerla medio segundo. Última ejecución (10/10/2026): 15 de 15 notas, sin errores de consola, sin desborde horizontal a 390 px. **No se probó con un violín real.**

## Precisión del afinador

```bash
node tests/js/afinador-precision.cjs
```

Pasa el detector (`public/js/afinador-yin.js`) por todo el registro de violín (Sol3 a Mi7), viola (Do3 a La6), violonchelo (Do2 a Mi6) y contrabajo (Mi1 a Do5), con el perfil de cada instrumento, a 44,1 y 48 kHz, con desafinaciones conocidas y dos tipos de señal: limpia, y con la fundamental más débil que el segundo armónico más ruido. Última ejecución (13/10/2026), 1900 mediciones por escenario: error medio 0,01 a 0,04 cents, peor caso 0,12 cents (señal limpia) y 0,70 cents (fundamental débil y ruido), sin errores de octava. En el navegador, con un oscilador como micrófono, el afinador leyó +3 cents exactos en contrabajo (Mi1 = 41,20 Hz), violonchelo y viola. Son señales sintéticas: con un micrófono real influyen el ruido de la sala y la calidad del micrófono.

## Digitalizador con Audiveris real

Se probó con Audiveris 5.7.0 (Linux) y un PDF de prueba de 7 páginas generado por computadora, digitalizando solo las páginas 4 y 6. Las 2 páginas se procesaron en unos 22 segundos. Se reconocieron 4 ejercicios. La comprobación de compases marcó como dudosos los 2 que tenían figuras mal leídas. No se probó con un escaneo real de un método impreso, que suele dar peores resultados.

## Sitio público (legal, SEO, cookies, anti-spam)

- `tests/Feature/SitioPublicoTest.php`: páginas legales, metas y datos estructurados, `noindex` en zonas privadas, sitemap, robots, 404,
  forzado de HTTPS, anti-spam, WhatsApp, analítica y casilla de privacidad en el registro. En `phpunit*.xml` se pone
  `MSEA_ANTISPAM=false` para que los demás tests no deban enviar el campo de tiempo; este test lo activa a propósito.
- `tests/e2e/sitio-publico.cjs` (navegador real): aviso de cookies, botón único, WhatsApp, rastreo de enlaces rotos, 404, inicio de sesión
  con anti-spam, y que los paneles no cargan analítica. Uso: `BASE=http://127.0.0.1:8000 NODE_PATH=$(npm root -g) node tests/e2e/sitio-publico.cjs`.
- Accesibilidad: se usó `axe-core` (reglas WCAG 2 A/AA y buenas prácticas) en escritorio y móvil sobre las 8 páginas públicas.
