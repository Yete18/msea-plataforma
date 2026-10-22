# Informe final: auditoría, corrección y finalización de MSEA

> **Documento histórico (10/10/2026).** Lo reemplaza [INFORME_AUDITORIA_INTEGRAL_2026-10-17.md](INFORME_AUDITORIA_INTEGRAL_2026-10-17.md). Varias cosas descritas aquí cambiaron después (Bootstrap y Lucide se retiraron, la CSP ya no permite estilos en línea y el login no pide rol).

**Fecha:** 10/10/2026
**Base de partida:** el estado del repositorio antes de los cambios (guardado en el historial de Git).

## Clasificación usada

- **Implementado y probado:** hay código nuevo y una prueba automática que pasa, o una verificación en navegador.
- **Implementado, pendiente de validación:** el código está escrito pero no se pudo ejecutar o probar en este entorno.
- **Parcialmente implementado:** solo una parte funciona.
- **Pendiente:** no se hizo.

## 1. Resumen

- Se corrigieron las vulnerabilidades confirmadas en la auditoría: IDOR en tareas, login aceptando el hash como contraseña, farmeo de XP, API móvil sin límite de intentos y correos en mayúsculas.
- Se rehízo la gamificación sobre un libro de XP idempotente.
- Se completaron los paneles de los cuatro roles con datos reales, la gestión del director, los cursos y lecciones, y los archivos privados.
- Se aplicó la paleta oficial con iconos SVG y la interfaz en español.
- La app Flutter se reescribió sobre la nueva API.

| Resultado | Detalle |
|---|---|
| Archivos nuevos | 84 |
| Archivos modificados | 77 |
| Archivos eliminados | 0 |
| Pruebas | **104 pruebas y 487 aserciones, correctas en SQLite y en PostgreSQL 16** |

## 2. Diagnóstico inicial (resumen)

Lo que se encontró al empezar (el detalle está en el documento «Auditoría del sistema MSEA»):

- Un estudiante podía ver y entregar tareas de otros.
- El hash guardado servía como contraseña.
- La XP se podía repetir sin límite.
- La API móvil aceptaba la duración enviada por el cliente.
- Los paneles mostraban datos simulados y emojis.
- No existía el rol padre en la interfaz.
- Los archivos eran públicos.
- No había auditoría.

## 3. Seguridad

| Punto | Estado |
|---|---|
| Login sin aceptar el hash, mensaje único y tiempo constante | Implementado y probado |
| Límites de intentos (login web y móvil, recuperación, código, acciones sensibles) | Implementado y probado |
| IDOR en tareas, entregas, material y grabaciones | Implementado y probado |
| Sesión: regeneración, cuenta desactivada, cambio de rol y huella al cambiar la contraseña | Implementado y probado |
| Cambio de correo y contraseña con la contraseña actual | Implementado y probado |
| Recuperación: correos en minúsculas, intentos por código, Mailable y revocación de tokens | Implementado y probado |
| Tokens móviles con hash, caducidad, máximo de sesiones y revocación | Implementado y probado |
| CSP con nonce y cabeceras de seguridad | Implementado y probado |
| XSS en los cuatro paneles (prueba E2E en Chromium) | Implementado y probado |
| CSV sin inyección de fórmulas | Implementado y probado |
| Tabla `auditoria` y visor del director | Implementado y probado |
| Verificación por enlace del correo nuevo | **Pendiente** |
| `style-src` sin `'unsafe-inline'` y fuentes y Bootstrap locales | **Pendiente** (riesgo bajo, documentado) |

**Sanctum:** se evaluó y no se usa. Se mantiene un token propio con las mismas garantías, por los motivos que explica `MobileTokenService`. No se afirma que Sanctum esté integrado.

## 4. Gamificación

| Punto | Estado |
|---|---|
| Libro `xp_movimientos` con clave única (sin XP duplicada) | Implementado y probado |
| `xp_total` recalculado desde el libro y ranking sin usuarios inactivos | Implementado y probado |
| Práctica medida por el servidor, duración mínima y tope diario | Implementado y probado |
| Teoría corregida en el servidor, sin enviar la respuesta | Implementado y probado |
| XP de tarea única al calificar; entrega, devolución y nueva entrega | Implementado y probado |
| Niveles, rachas por día y logros aplicados una sola vez | Implementado y probado |
| Saldo inicial de XP existente al migrar | Implementado y probado (PostgreSQL) |
| Evaluación técnica de afinación y ritmo | **No se declara.** Las métricas se guardan como «no verificadas». |

## 5. Funcionalidades por rol

| Rol | Contenido | Estado |
|---|---|---|
| Director | Usuarios (activar, desactivar, cambiar rol, vincular padre e hijo), catálogos, cursos, comunicados, auditoría y reportes CSV | Implementado y probado |
| Profesor | Crear, editar y borrar tareas (individuales o por elenco, con material), revisar entregas, escuchar grabaciones, calificar o devolver, mensajes | Implementado y probado |
| Estudiante | Datos reales, tareas con entrega de audio, práctica, teoría, cursos y lecciones, logros, ranking y notificaciones | Implementado y probado |
| Padre o madre | Hijos vinculados: práctica de 7 días, tareas y notas, logros, reporte CSV y mensajes con el profesor | Implementado y probado |
| Horario de clases | Se quitó el horario ficticio. No hay tabla de horarios. | **Pendiente** |
| Pagos | Fuera del alcance | **Pendiente** |

## 6. Cursos, módulos y lecciones

| Punto | Estado |
|---|---|
| El director crea y publica cursos, módulos, lecciones y preguntas | Implementado y probado |
| El estudiante solo ve lo publicado y registra su progreso por lección (con XP única) | Implementado y probado |

## 7. Archivos y grabaciones

| Punto | Estado |
|---|---|
| Disco privado, nombre UUID, MIME real (finfo), límites de tamaño y descarga autorizada | Implementado y probado |
| Grabación desde el micrófono del navegador (`microphone=(self)`) | Implementado, pendiente de validación en dispositivos reales |

## 8. Interfaz

| Punto | Estado |
|---|---|
| Sin datos simulados (estados vacíos reales) | Implementado y probado (revisión visual de los 4 roles) |
| Emojis sustituidos por un sprite SVG con iconos propios de instrumentos | Implementado y probado |
| Paleta oficial, fuentes Nunito y Baloo 2, buen contraste en botones verdes | Implementado y probado (capturas) |
| Responsive: escritorio y móvil sin desbordes | Implementado y probado (capturas a 390 px) |
| Textos y validaciones en español | Implementado y probado |
| Modo oscuro revisado con la paleta nueva | **Parcialmente implementado** |

Nota: Google Fonts no cargaba en el entorno de prueba, así que las capturas usan fuentes de reserva.

## 9. API y Flutter

| Punto | Estado |
|---|---|
| API `/api/mobile/*`: login, perfil, tareas, entrega, práctica (inicio y fin), notificaciones, sesiones y cierre de sesión | Implementado y probado |
| App Flutter: token en `flutter_secure_storage`, restaurar sesión, URL por `--dart-define`, HTTPS obligatorio en release, errores de red, pantallas por rol, práctica medida por el servidor, sesiones, sin credenciales precargadas | **Implementado, pendiente de validación.** No había Flutter SDK en el entorno: no se compiló ni se ejecutó `flutter test`. |
| Subir grabaciones desde la app | **Pendiente** (la API ya lo admite) |
| Instalable para Android o iOS | **Pendiente.** No se generaron las carpetas de plataforma ni se compiló. **No existe todavía una app instalable.** |

## 10. Base de datos y migraciones

| Punto | Estado |
|---|---|
| Migración aditiva `2026_10_10_000001_*` con `down()` | Implementado y probado: `migrate`, `rollback` y `migrate` en PostgreSQL 16 |
| Índice único `lower(correo)` (solo si no hay duplicados) | Implementado y probado |
| Seeder de demostración bloqueado en producción y sin contraseña vacía | Implementado y probado |
| Fusión de instrumentos duplicados en bases existentes | **Pendiente.** Manual, desde *Catálogos*. |
| Tablas antiguas `practicas`, `feedback` y `recompensas` | Se conservan sin cambios |

## 11. Pruebas

| Prueba | Resultado |
|---|---|
| `php artisan test` (SQLite) | 104 pruebas, 487 aserciones, todas correctas |
| `phpunit -c phpunit.pgsql.xml` (PostgreSQL 16, base `msea_testing` separada) | 104 pruebas, 487 aserciones, todas correctas |
| E2E de XSS en Chromium (Playwright) | Ninguna carga se ejecutó |
| `flutter test` | **No ejecutado** (no hay SDK) |
| Envío real de correo SMTP | **No probado** (en pruebas se usa `Mail::fake`) |

La guía completa está en `TESTING.md`.

## 12. Producción, privacidad y próximos pasos

- `DEPLOYMENT.md` explica el despliegue, la actualización con copia de seguridad previa y la reversión.
- `SECURITY.md` resume los controles de seguridad.
- `.env.example` documenta todas las variables `MSEA_*`.
- No se hizo ningún despliegue ni se tocó ninguna base de producción.
- No se copiaron secretos en este informe.
- **Acción para el responsable:** en `.env` local, poner `APP_LOCALE=es` para que los mensajes de validación salgan en español.

### Próximos pasos recomendados

1. Instalar Flutter y ejecutar `flutter create .`, `flutter analyze` y `flutter test`, y probar la app en Android.
2. Configurar el SMTP real y probar la recuperación de contraseña.
3. Ejecutar la migración en una copia de la base real y revisar los posibles correos duplicados.
4. Unificar los instrumentos duplicados desde *Catálogos*.
5. Alojar las fuentes localmente y eliminar `'unsafe-inline'` de `style-src`.
