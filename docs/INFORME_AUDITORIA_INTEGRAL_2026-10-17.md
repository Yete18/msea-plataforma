# Auditoría integral, rediseño y mejora de MSEA

**Fecha:** 17/10/2026 · **Rama:** `rediseno-ux` · **Respaldo previo:** etiqueta `respaldo-antes-rediseno-2026-10-17`

Este informe cierra la auditoría de 62 hallazgos y su corrección en seis fases (A a F). Cada fase se probó por separado y se guardó en su propio commit, para poder identificar la causa de cualquier regresión.

## Decisiones de la fundación que guiaron el trabajo

- **Tareas de elenco (SEC-01):** cada profesor ve y califica solo a sus propios alumnos, también dentro de un elenco compartido.
- **Ranking (SEC-04):** por elenco, con el nombre de pila de los demás y sin opción de ocultarse.
- **Perfiles nuevos (RN-01):** Secretaría, Subgerencia financiera y Director de orquesta quedan fuera del alcance.
- **Inicio de sesión sin elegir el rol (UX-07):** se implementó como prueba; se revierte en un solo commit si no convence.
- **Correo propio (SMTP):** sin decidir. La verificación de correo al registrarse queda apagada hasta tenerlo.
- **Limpieza:** se borran los archivos y el código sin uso, y se actualiza la documentación.

## Resultado por hallazgo

Estados: **Resuelto** (con prueba automática o verificación en navegador), **Parcial**, **Pendiente**, **Fuera de alcance**.

| Fase | Hallazgo | Estado | Cómo quedó |
|---|---|---|---|
| A | BD-03 Reversión que borraba el historial | Resuelto | `ReversionSegura` bloquea `migrate:rollback` en producción salvo `MSEA_PERMITIR_ROLLBACK`; la guía indica restaurar un respaldo |
| A | SEC-01 Profesor veía entregas de alumnos ajenos en un elenco | Resuelto | Filtro por `id_profesor` en `TareaService`; `FaseAIntegridadTest` |
| A | BD-01 Borrar un elenco con tareas daba error 500 | Resuelto | Mensaje claro y confirmación; claves `RESTRICT` |
| A | BD-02 Cascadas que borraban tareas, entregas y XP | Resuelto | Migración `2026_10_17_000001` (`RESTRICT`) |
| A | BD-13 Sin respaldo automático | Parcial | Procedimiento y script en `DEPLOYMENT.md`; falta programarlo en el servidor y hacer el primer simulacro |
| A | SEC-04 Ranking con datos de menores de toda la academia | Resuelto | Ranking por elenco, solo nombre de pila |
| A | SEC-02 Registro sin verificar el correo y vínculo con Google | Parcial | Si el dueño real entra con Google a una cuenta registrada sin verificar, se anula la contraseña anterior y se cierran sus sesiones; verificar el correo al registrarse espera el SMTP |
| A | SEC-03 Fuerza bruta lenta del código de recuperación | Resuelto | Límites por correo e IP y por código |
| A | BD-09 Borrar una tarea eliminaba entregas sin calificar | Resuelto | Una tarea con entregas no se borra |
| A | ARQ-03 Fotos de menores en el disco público | Resuelto | Disco privado y `/archivos/fotos/{id}` con permiso; comando `msea:fotos-privadas` |
| A | SEC-08 Disco privado con `serve => true` | Resuelto | Se retiraron las rutas `storage/{path}` |
| B | BD-04 Instalación limpia sin catálogos | Resuelto | `CatalogosSeeder` idempotente y apto para producción |
| B | BD-05 Registro creaba instrumentos con cualquier texto | Resuelto | Solo instrumentos existentes |
| B | BD-06 Claves foráneas sin índice | Resuelto | Migración `000004` (`CONCURRENTLY`) |
| B | BD-07 Sin restricciones de dominio | Resuelto | Migración `000005` (`CHECK`, `NOT VALID` si hay filas antiguas) |
| B | BD-10 Prácticas simultáneas inflaban los minutos | Resuelto | Sesión abierta se cierra sola; bloqueo de fila |
| B | ARQ-01 Consultas N+1 | Resuelto | Consultas agrupadas en los paneles del profesor y cursos |
| B | ARQ-02 Digitalizador síncrono | Resuelto | Cola `database-largo` con trabajo único; falta activar el worker en el servidor |
| B | ARQ-04 Contraseñas antiguas en texto plano | Resuelto | Migración `000003` y login sin rama de texto plano |
| B | BD-08 Instrumento y nivel como texto; JSON como `text` | Pendiente | Requiere migrar datos; espera aprobación |
| B | BD-11 Esquema distinto entre entornos | Resuelto | Consulta de verificación en `DEPLOYMENT.md` |
| B | SEC-06 Token de Google en el log | Resuelto | Solo se registra la clase del error |
| B | SEC-07 Valores inseguros por defecto | Resuelto | `APP_DEBUG` bloqueado en producción, cookie segura, sin envío de correo falso |
| B | SEC-09 Sin hosts de confianza | Resuelto | `trustHosts` con `APP_URL` y `MSEA_HOSTS` |
| B | SEC-11 Un director podía cambiar el correo de otro | Resuelto | Bloqueado en `GestionUsuariosService` |
| B | BD-14 Fechas sin zona horaria | Parcial | Zona de la sesión de PostgreSQL alineada; `timestamptz` diferido |
| B | BD-15 Migración dependiente del código | Resuelto | Semilla congelada en `database/data/` |
| B | ARQ-05 Lógica mezclada y duplicada | Pendiente | Refactor sin cambio visible; anotado en `TECH_DEBT.md` |
| B | ARQ-06 Modelos desalineados | Resuelto | `$fillable` completos; modelos sin uso retirados en la fase F |
| B | ARQ-07 Sin tareas programadas ni rotación de logs | Resuelto | `msea:limpiar` diario y logs diarios |
| B | ARQ-08 Ediciones sin transacción | Resuelto | Transacciones en usuarios y tareas |
| C | UX-02 Eliminar elenco sin confirmar | Resuelto | Confirmación accesible |
| C | UX-03 Error y éxito se veían iguales | Resuelto | Componente `x-alerta` con color, icono y rol |
| C | A11Y-01 Etiqueta de contraseña sin conectar | Resuelto | |
| C | A11Y-03 Contraste insuficiente | Resuelto | Tokens con 4,5:1; axe sin fallos en las 4 vistas, 3 anchos y 2 temas |
| C | UX-04 Registro perdía instrumento y nivel | Resuelto (fase F) | Valores conservados, error junto al campo y paso correcto abierto |
| C | UX-05 Navegación móvil distinta en cada panel | Resuelto | `x-panel.layout` común |
| C | UX-08 Panel del director de 10 secciones | Resuelto | Navegación agrupada y subpáginas |
| C | FE-01 Lucide completo | Resuelto | Retirado; sprite propio |
| C | FE-02 Bootstrap en las páginas de acceso | Resuelto | Retirado |
| C | FE-03 Tokens fragmentados | Resuelto | `tokens.css` único |
| C | FE-04 CSS duplicado | Resuelto | Hojas antiguas borradas; reglas muertas podadas en la fase F |
| C | FE-05 Orden de carga y 72 `!important` | Resuelto | Capas CSS y orden fijo; quedan 10 `!important` justificados |
| C | UX-10 Ortografía, género y números | Resuelto | Tildes en pantallas y mensajes del servidor, números `1.234`, textos sin género; el contenido de teoría de demostración se corrige al cargar el real |
| D | RN-01 Perfiles nuevos | Fuera de alcance | Decisión de la fundación |
| D | UX-06 Demasiados indicadores y presión por la racha | Resuelto | Racha y XP sin duplicados; aviso amable; un día de descanso (`MSEA_RACHA_DIAS_DESCANSO`) |
| D | UX-07 El login obliga a elegir el rol | Resuelto (prueba) | Web y API móvil sin rol; un rol antiguo se ignora |
| D | UX-09 El estudiante editaba CI, dirección y nacimiento | Resuelto | Solo la dirección los corrige |
| D | SEC-05 XP de teoría tras ver la respuesta | Resuelto | XP completa solo al primer intento; una cuarta parte después |
| E | A11Y-02 Errores no asociados a sus campos | Resuelto | `aria-invalid`, `aria-describedby` y foco al primer error, también desde el servidor |
| E | A11Y-05 Modales sin gestión del foco | Resuelto | Foco atrapado, Escape y devolución del foco |
| E | A11Y-06 Botones pequeños | Resuelto | Todos los controles de al menos 44 × 44 px (medido) |
| E | A11Y-07 Cambiar de sección no se anunciaba | Resuelto | Título de la pestaña y foco al encabezado |
| E | A11Y-08 Familia: IDs duplicados y pestañas incompletas | Resuelto (fase C) | |
| E | A11Y-12 Tablas con desplazamiento sin foco | Resuelto (fase C) | |
| E | FE-06 Tema oscuro tardío | Resuelto | Aplicado en el `<head>`; sigue la preferencia del sistema |
| E | FE-08 Sprite incrustado | Resuelto | `public/assets/img/iconos.svg`, versionado y con caché |
| E | FE-09 `unsafe-inline` en estilos | Resuelto | Cero atributos `style`; CSP `style-src 'self'` |
| E | MOV-01 App Flutter sin compilar | Parcial | Login sin rol en app y API; falta `flutter create` y prueba en un teléfono (requiere el SDK) |
| F | BD-12 Tablas y modelos sin uso | Resuelto | 4 modelos retirados; las tablas antiguas se conservan con sus datos (decisión documentada) |
| F | FE-07 Código e imágenes sin uso | Resuelto | Reglas CSS, código JS y modelos muertos retirados; 6 imágenes y un `robots.txt` antiguo para borrar en el equipo |
| F | LAND-01 Portada fuera del estilo de la taste-skill | Pendiente (opcional) | Solo estético; la portada ya está aprobada |

**Totales:** 54 resueltos, 4 parciales (BD-13, SEC-02, BD-14 y MOV-01), 3 pendientes (BD-08, ARQ-05 y LAND-01) y 1 fuera de alcance (RN-01). Los números de A11Y-04, A11Y-09, A11Y-10 y A11Y-11 no tienen hallazgo descrito en el informe original.

## Validación

| Prueba | Resultado (17/10/2026) |
|---|---|
| PHPUnit en PostgreSQL 16 | 211 pruebas, 1329 aserciones, todas correctas |
| PHPUnit en SQLite | 209 correctas y 2 omitidas (solo PostgreSQL), 1326 aserciones |
| Recorridos por rol (`tests/e2e/recorridos-por-rol.cjs`, CSP real) | 36 de 36 comprobaciones; sin errores JS, violaciones de CSP ni respuestas 4xx/5xx |
| `escala.cjs`, `digitalizador.cjs`, `sitio-publico.cjs` | Correctos |
| XSS almacenado (`xss.cjs`) | Ninguna carga se ejecutó; texto escapado |
| axe (WCAG 2 A y AA) | Sin infracciones en los cuatro paneles, acceso y páginas públicas, a 1366, 768 y 390 px, en tema claro y oscuro |
| Rutas | +3 (`archivos/fotos/{id}`, `dashboard-admin/perfil`, `dashboard-admin/profesores`), −2 (`storage/{path}`, que exponía el disco privado) |
| Migraciones | +5, todas aditivas (`2026_10_17_000001` a `000005`); probadas en instalación limpia y sobre datos antiguos |

**No se probó:** un lector de pantalla real, la app en un teléfono, el envío de correo con SMTP y el botón de Google con un `GOOGLE_CLIENT_ID` real.

## Qué hacer en el servidor al desplegar

Seguir `DEPLOYMENT.md`, sección *Actualizar una instalación existente*: respaldo, migraciones, `CatalogosSeeder`, `msea:fotos-privadas`, cron de `schedule:run`, worker `database-largo`, borrar `public/robots.txt` si existe y la consulta de verificación del esquema.
