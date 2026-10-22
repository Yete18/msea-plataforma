# Arquitectura de MSEA

MSEA es una aplicación Laravel 12 que usa PostgreSQL en producción. Tiene un panel web para cuatro roles (director, profesor, estudiante y padre o madre) y una API móvil que consume la app Flutter de `mobile_flutter/`.

## Estructura

| Carpeta | Responsabilidad |
|---|---|
| `app/Http/Controllers` | Reciben la petición, validan con Form Requests y delegan en un servicio. No contienen SQL de negocio. |
| `app/Http/Controllers/Api/MobileController.php` | Expone la API `/api/mobile/*`. |
| `app/Http/Requests` | Validación. El trait `Concerns/NormalizaCorreo` normaliza el correo (`trim` y minúsculas) antes de validar. |
| `app/Http/Middleware` | `AuthenticateSession` para la web, `AuthenticateMobileToken` (`auth.mobile`) para la API y `SecurityHeaders` para cabeceras y CSP. |
| `app/Services` | Lógica de negocio (ver la tabla siguiente). |
| `app/Models` | Modelos Eloquent sobre las tablas reales (`usuarios`, `estudiantes`, etc.). |
| `config/msea.php` | Reglas configurables: XP, topes, racha (días de descanso), tamaños y tipos de archivo, vigencia de tokens, recuperación de contraseña y colas. |
| `database/migrations` | Esquema reproducible. Desde la fase de seguridad y gamificación, todas las migraciones son aditivas. |
| `database/seeders` | `CatalogosSeeder` (roles, niveles, instrumentos, tipos y logros; apto para producción e idempotente) y `MseaDemoSeeder` (solo desarrollo). `database/data/` guarda semillas congeladas que usan las migraciones. |
| `resources/views` | Blade, con un archivo por panel, `admin/*` para la gestión del director y `components/` (`x-panel.layout`, `x-alerta`, `x-alertas-sesion`). |
| `public/js`, `public/css` | JavaScript y CSS sin build. El sistema de diseño está en `tokens.css`, `componentes.css` y `panel-layout.css`; `msea-ui.js` y `panel-layout.js` son la base compartida del JavaScript. |
| `public/assets/img/iconos.svg` | Sprite de iconos SVG (Lucide e instrumentos propios), un archivo que el navegador guarda en caché. |
| `mobile_flutter/` | Cliente móvil (ver su README). |

### Servicios

| Servicio | Qué hace |
|---|---|
| `AuthService` | Login con temporización constante, normalización de correo e instrumentos, y registro público de estudiantes. |
| `CuentaSeguridadService` | Cambio de contraseña y de correo (pide la contraseña actual), huella de credencial y cierre de otras sesiones. |
| `PasswordResetService` | Códigos de 6 dígitos con hash, caducidad, límite de intentos por código y revocación de tokens al restablecer. |
| `MobileTokenService` | Emite, valida, lista y revoca tokens móviles. Guarda el hash SHA-256, la caducidad y un máximo de sesiones por usuario. |
| `AuditLogger` | Escribe en `auditoria` y elimina las claves sensibles de los detalles. |
| `GamificationService` | Libro de XP (`xp_movimientos`), niveles, rachas, logros y bonificaciones. Todo es idempotente. |
| `PracticaService` | Prácticas con inicio y fin medidos por el servidor, duración mínima y tope diario de XP. |
| `TeoriaService` | Corrige las preguntas de teoría en el servidor. Registra `teoria_intentos` y da la XP una sola vez. |
| `CursoService` | Cursos, módulos y lecciones publicados, más el progreso del estudiante (`leccion_progreso`). |
| `TareaService` | Visibilidad de tareas, entregas, devoluciones, calificación con XP única, y permisos sobre archivos y material. |
| `ArchivoPrivadoService` | Guarda archivos en el disco privado, con detección real del MIME (finfo), nombre UUID y descarga autorizada. |
| `ProfesorDashboardService`, `PadreDashboardService`, `StudentDashboardService`, `AdminDashboardService` | Datos de cada panel. |
| `GestionUsuariosService` | Lo que gestiona el director: activar o desactivar cuentas, cambiar roles, vincular padres e hijos y catálogos. |
| `GoogleAuthService` | Inicio de sesión con Google: verifica el ID token y vincula por correo verificado solo cuentas existentes. |
| `HabitosService` | Meta diaria de práctica, minutos por día, semana actual, títulos de nivel y detección de subidas de nivel y logros nuevos para celebrarlos. |
| `EscalaService` | Biblioteca técnica: notas de cada escala con frecuencia exacta (La4 = 440 Hz, temperamento igual), ortografía por grados, armadura, digitación, validación de la configuración del profesor (octavas, dirección, ritmo, tempo, compás, tolerancia) y del resultado medido en el navegador. La cuerda y el dedo en primera posición se calculan solos para cada instrumento (`EscalaService::INSTRUMENTOS`: cuerdas, registro, clave de Sol, Do o Fa, y el contrabajo escrito una octava arriba). Una tarea de escala se toca en el instrumento de cada estudiante: se busca la escala del mismo nombre en su instrumento. El afinador del navegador está en `public/js/afinador-yin.js` (YIN con refinamiento por varios períodos). |
| `TecnicaService` | Ejercicios técnicos (patrones de dedos generados para cada instrumento) y métodos digitalizados: notas, configuración (modo afinación o velocidad) y datos para la práctica guiada. |
| `DigitalizadorService`, `MusicXmlLector`, `NotacionTexto` | Digitalizador de métodos en PDF: páginas elegidas, reconocimiento con Audiveris, lectura de MusicXML, notación de texto para corregir y publicación en la biblioteca. Ver `docs/DIGITALIZADOR.md`. |
| `MensajeService` | Mensajes entre profesor y familia o estudiante, solo entre personas vinculadas. |
| `CsvExporter` | CSV con BOM UTF-8 que neutraliza las fórmulas (`=`, `+`, `-`, `@`). |
| `ProfilePhotoService` | Fotos de perfil, compatibles con el formato base64 antiguo. |

## Autenticación

**Web.** La persona escribe su correo y su contraseña; el rol sale de la cuenta (no se elige en el formulario desde la fase D). Se usa una sesión manual (`usuario_id`, `rol`) y no el guard de Laravel, para no romper las vistas existentes. En cada petición, `AuthenticateSession`:

1. Carga el usuario desde la base de datos y rechaza las cuentas desactivadas (`usuarios.activo`).
2. Comprueba que el rol de la sesión siga coincidiendo con el de la base de datos.
3. Compara la huella de credencial guardada en la sesión con un hash de la contraseña actual. Si alguien cambió la contraseña, las demás sesiones se cierran.

Al iniciar sesión se regenera el ID de sesión, y al salir se invalida la sesión.

**API móvil.** `POST /api/mobile/login` recibe correo y contraseña; el campo `rol` es opcional y se ignora (las versiones antiguas de la app lo envían). Se usa un token Bearer propio y no Sanctum. El motivo está documentado en `MobileTokenService`: Sanctum espera el modelo y el guard estándar de Laravel, y el sistema usa la tabla `usuarios` con sesión manual. Funcionamiento:

- En la base de datos solo se guarda el hash del token.
- El token caduca a los 30 días (configurable).
- Cada usuario puede tener como máximo 5 sesiones. Al superar el máximo se revocan las más antiguas.
- Al cambiar o restablecer la contraseña se revocan todos los tokens.

## Gamificación

- **Libro de XP.** Cada otorgamiento es una fila en `xp_movimientos` con una `clave` única (por ejemplo `tarea:15`, `teoria:3`, `logro:2`, `practica:88`), así que repetir una acción no duplica la XP. `gamificacion_perfiles.xp_total` se recalcula como la suma del libro, y la vista `ranking_estudiantes` lee ese total (solo de usuarios activos).
- **Niveles.** Hay un nivel cada 500 XP (configurable).
- **Rachas.** Se cuentan por día calendario con actividad válida. Un día sin actividad entre dos días de práctica no corta la racha (`msea.racha.dias_descanso`, por defecto 1): no suma, pero tampoco la reinicia. El aviso del panel es positivo y solo aparece por la tarde.
- **Ranking.** Cada estudiante se compara solo con su elenco (o, sin elenco, con los alumnos de su profesor). De los demás se muestra solo el nombre de pila.
- **Prácticas.** Las mide el servidor. Las de menos de 30 s no dan XP, y la XP de práctica tiene un tope de 150 por día. Las métricas que envía el cliente (BPM, precisión) se guardan con `metricas_verificadas = false` y no afectan a la XP. **El sistema no evalúa técnicamente la interpretación del instrumento.**
- **Teoría.** El servidor corrige. La XP completa se da solo si se acierta al primer intento; tras un error, una cuarta parte (`TeoriaService::xpTrasError`). Cada pregunta paga una sola vez.
- **Tareas.** Una entrega pasa por los estados entregada, devuelta y calificada. La XP de una tarea se otorga una sola vez, al calificarla.

## Archivos

Las grabaciones, el material de las tareas y los adjuntos van al disco `local` (privado, `storage/app/private`). Se descargan con `ArchivoController`, que comprueba el permiso con `TareaService::puedeVerEntrega` o `puedeVerMaterial`. Nunca hay URLs públicas directas.

Las fotos de perfil siguen en el disco `public` (`php artisan storage:link`).

## Base de datos

| Migración | Contenido |
|---|---|
| `2026_05_26_000001_create_msea_schema.php` | Esquema base. Tiene una protección: no recrea tablas si ya existen. |
| `2026_10_10_000001_msea_seguridad_y_gamificacion.php` | Columnas, tablas, índices y vista de esta fase. Es aditiva y tiene un `down()` completo (probado en PostgreSQL 16). |
| `2026_10_16_000001_msea_tecnica_y_digitalizador.php` | Tablas `digitalizaciones` y `digitalizacion_paginas`, y los ejercicios de técnica de mano izquierda para los cuatro instrumentos. |
| `2026_10_15_000001_msea_escalas_viola_chelo_contrabajo.php` | Las mismas 48 escalas, con el mismo nombre, para viola, violonchelo y contrabajo (registro y octavas propios). |
| `2026_10_14_000001_msea_escalas_mayores_menores.php` | 12 escalas mayores y 12 menores (natural, armónica y melódica) para violín, de 1 a 3 octavas. |
| `2026_10_13_000001_msea_biblioteca_escalas.php` | Tabla `biblioteca_ejercicios`, columnas `tareas.id_biblioteca`, `tareas.config_escala` y `entregas_tareas.resultado_escala`, y la escala de La mayor. Aditiva, con `down()`. |
| `2026_10_17_000001_msea_integridad_referencial.php` y `000002_msea_origen_cuenta.php` | Fase A: claves foráneas que faltaban y origen de la cuenta (registro, director, Google). |
| `2026_10_17_000003_msea_hashear_contrasenas_antiguas.php` | Convierte a hash las contraseñas antiguas guardadas en texto plano, sin cambiar su valor. |
| `2026_10_17_000004_msea_indices_claves_foraneas.php` | Índices para las claves foráneas (en PostgreSQL, `CONCURRENTLY`). |
| `2026_10_17_000005_msea_restricciones_dominio.php` | `CHECK` de estados y valores, y nombre de elenco único sin distinguir mayúsculas. Si hay filas antiguas que no cumplen, la restricción se crea `NOT VALID` y se avisa en el log. |

Las tablas antiguas `practicas`, `feedback` y `recompensas` se conservan. El código nuevo usa `practicas_autonomas` y `xp_movimientos`.

## Frontend

- **Sistema de diseño.** `tokens.css` define colores, tipografía, espacios, radios y sombras para el tema claro y el oscuro (`:root.theme-dark`); `componentes.css` (capas `base` y `componentes`) define botones, alertas, tarjetas, formularios, tablas y diálogos; `panel-layout.css` define la estructura de todos los paneles (barra lateral fija en escritorio, cajón con `aria-expanded` e `inert` hasta 1023 px). Cada panel suma su hoja (`admin.css`, `profesor.css`, `estudiante.css`, `padre.css`, `auth.css`). Todo texto cumple 4,5:1 y todo control mide al menos 44 px.
- **Fuentes.** Nunito para el texto y Baloo 2 para los títulos. Bravura (SIL OFL, `public/fonts/bravura.woff2`) dibuja la clave, las alteraciones y las figuras rítmicas.
- **Iconos.** Sprite externo `public/assets/img/iconos.svg` (Lucide e iconos propios de violín, viola, violonchelo, contrabajo, afinación y ritmo). En Blade: `<use href="{{ $iconos }}#i-nombre"/>` (`$iconos` lleva la versión del archivo); en JavaScript: `MSEA_UI.icon('nombre')`. El sistema no usa emojis.
- **JavaScript.** `window.MSEA_UI` ofrece `esc`, `safeUrl`, `api` (fetch con CSRF), `toast`, `toastTrasRecargar`, `conCarga`, `mostrarErrores`, `dialogo` (foco atrapado, Escape y devolución del foco), `anunciarVista` y `icon`. `window.MSEA_CAMPOS` (en `panel-layout.js`) asocia cada error de validación a su campo con `aria-invalid` y `aria-describedby`, también los que devuelve el servidor. Todo el HTML dinámico se escapa con `esc`.
- **Sin librerías externas.** Nunito y Baloo 2 se sirven desde `public/fonts` (`css/fuentes.css`). Bootstrap y Lucide se retiraron en la fase C.
- **Tema oscuro.** `partials/tema-inicial` aplica la clase `theme-dark` en `<html>` antes de pintar (elección guardada o preferencia del sistema); `theme-toggle.js` solo sincroniza el botón.
- **Experiencia.** Menú de usuario con «Cerrar sesión» en la cabecera, barra lateral con el cierre de sesión siempre visible, navegación inferior en el móvil del estudiante, confeti y modales al subir de nivel o desbloquear logros (`MSEA_UI.celebrar`, `modal`, `xpFlotante`), respetando `prefers-reduced-motion`.
- **CSP.** Los `<script>` en línea llevan un nonce por petición (`$cspNonce`). Los estilos solo pueden venir de hojas propias: no hay atributos `style`; las barras de progreso usan `data-ancho` o `data-alto` y `panel-layout.js` aplica la medida.
- **Idioma.** La interfaz y los mensajes de validación están en español (`lang/es`).

## Sitio público

Las páginas sin sesión (portada, login, registro, recuperación, textos legales, errores) comparten `partials/seo` (metadatos, JSON-LD),
`partials/cookies` (aviso y analítica con consentimiento), `partials/whatsapp`, `partials/antispam` y `partials/enlaces-legales`.
Los datos de contacto, WhatsApp y analítica salen de `config/msea.php` (`sitio`), que lee el `.env`. Middleware nuevos:
`ForzarHttps` (producción) y `ProteccionSpam` (alias `antispam`, en login, registro y recuperación). `SecurityHeaders` añade
`X-Robots-Tag: noindex` a todo lo que no sea portada o textos legales, y abre la CSP solo para la analítica configurada.
Ver `docs/SEO_Y_PRESENCIA.md`.
