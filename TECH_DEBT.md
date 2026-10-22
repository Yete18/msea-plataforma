# Deuda técnica de MSEA

Pendientes conocidos, revisados el 17/10/2026 (auditoría integral, fases A a F). Los puntos marcados como ~~resuelto~~ se cerraron en la auditoría de octubre de 2026 y se conservan para dejar constancia.

## Autenticación

- Migrar gradualmente de la sesión manual al guard nativo de Laravel (`Auth::attempt`) cuando `Usuario` sea el proveedor principal. Hoy `AuthenticateSession` comprueba el estado activo, el rol y la huella en cada petición.
- ~~Registrar inicios de sesión y auditoría de accesos~~. Resuelto con la tabla `auditoria`.
- ~~Límites de intentos en login y recuperación~~. Resuelto.
- Verificar el correo nuevo mediante un enlace antes de aplicar el cambio. Hoy solo se exige la contraseña actual.
- Autenticación en dos pasos (opcional) para director y profesores.
- Evaluar Sanctum si en el futuro se adopta el guard nativo. Hoy se usan tokens propios (`MobileTokenService`).

## Autorización

- ~~Reglas para los recursos propios (tareas, entregas, archivos)~~. Resuelto en `TareaService`, con pruebas de IDOR.
- Pasar las comprobaciones de `TareaService` y `MensajeService` a Policies de Laravel cuando se adopte el guard nativo.

## Base de datos

- ~~Tabla `entregas_tareas` y adjuntos~~. Resuelto: archivos privados con metadatos.
- Las tablas antiguas `practicas`, `progreso`, `feedback`, `recompensas` y `estudiante_recompensa` se conservan con sus datos (BD-12): retirarlas exige un volcado previo y una migración documentada. En la fase F se borraron los modelos que ya nadie usaba (`EstudianteRecompensa`, `RankingEstudiante`, `TareaEjercicio`, `XpMovimiento`); las tablas siguen intactas.
- Fusionar los duplicados de instrumentos y niveles en bases existentes. Lo hace el director a mano desde *Catálogos*.
- El índice único `lower(correo)` no se crea si ya había duplicados. Resolverlos a mano y volver a ejecutar la parte del índice.
- Horarios de clase: no existe ninguna tabla para ellos. Se quitó el horario ficticio del panel, y falta modelarlo.
- Zona horaria: desde 2026-10 la aplicación usa `America/La_Paz` (antes UTC) y la sesión de PostgreSQL usa la misma zona (`DB_TIMEZONE`). Las fechas guardadas antes del cambio quedaron en UTC (4 horas de diferencia). Se decidió **no** convertir las columnas a `timestamptz` todavía: exige mover datos y probar cada reporte. Queda pendiente (BD-14).
- ~~Catálogos solo en los datos de demostración~~. Resuelto: `CatalogosSeeder`, apto para producción (BD-04).
- ~~El registro público creaba instrumentos con cualquier texto~~. Resuelto (BD-05).
- ~~Claves foráneas sin índice y estados sin restricciones~~. Resuelto con dos migraciones aditivas (BD-06, BD-07).
- ~~Una migración dependía de `TecnicaService`~~. Resuelto: la semilla está congelada en `database/data/` (BD-15).
- ~~Contraseñas antiguas en texto plano~~. Resuelto: la migración las convierte a hash con el mismo valor y el login ya no las acepta (ARQ-04).
- Instrumento y nivel siguen guardados como texto en algunas tablas, y varias columnas JSON son `text` (BD-08). Requiere migrar datos; pendiente de aprobación.

## Frontend

- ~~Datos simulados en los paneles~~. Resuelto: los paneles muestran datos reales o estados vacíos.
- ~~Emojis en la interfaz~~. Resuelto con el sprite SVG.
- ~~La CSP necesitaba `style-src 'unsafe-inline'`~~. Resuelto (FE-09): sin atributos `style`; las barras usan `data-ancho`/`data-alto`. Verificar el botón de Google con un `GOOGLE_CLIENT_ID` real, porque su hoja externa es la única excepción.
- ~~Alojar localmente Google Fonts y Bootstrap~~. Resuelto: `public/fonts`; Bootstrap y Lucide se retiraron en la fase C (sistema de diseño propio).
- ~~Modo oscuro sin revisar y con destello al cargar~~. Resuelto (FE-06): se aplica antes de pintar, sigue la preferencia del sistema y pasa axe en todas las pantallas.

## Móvil

- La app Flutter está escrita pero no se ha compilado ni probado en dispositivo (MOV-01, ver `mobile_flutter/README.md`). Falta `flutter create --platforms=android,ios .` y una prueba en un teléfono, que requieren el SDK de Flutter en el equipo de desarrollo. Desde la fase E la app ya no pide el rol al iniciar sesión.
- Falta subir grabaciones desde la app (la API ya lo admite).
- Faltan las funciones de profesor en la app (calificar). Hoy solo están en la web.

## Producto

- El módulo de pagos queda fuera del alcance.
- Evaluación técnica de afinación y ritmo: el sistema **no** la realiza. Las métricas del cliente se guardan como no verificadas. Implementarla de forma fiable requeriría análisis de audio validado con profesores.

## Producción

- Configurar en el servidor las copias de seguridad automáticas descritas en `DEPLOYMENT.md` y hacer el primer simulacro de restauración (BD-13).
- Configurar el SMTP real y probar el envío.
- Monitoreo de errores (Sentry o similar). ~~Rotación de logs y purga de datos vencidos~~: resuelto con `LOG_STACK=daily` y `msea:limpiar` programado (ARQ-07).
- Activar en el servidor el cron de `schedule:run` y el worker `database-largo` del digitalizador (ARQ-02).

## Pendientes de la auditoría integral (17/10/2026)

- **ARQ-05.** Las notificaciones se crean en tres lugares y el perfil se valida de tres formas. Unificarlo en un `NotificacionService` y un `PerfilService` es un refactor sin cambio visible; se dejó para no mezclarlo con el rediseño.
- **SEC-02.** Verificar el correo al registrarse requiere un SMTP real (hoy no hay). Mientras tanto, Google solo entra a cuentas que ya existen y, si el dueño real de un correo registrado sin verificar entra con Google, se anula la contraseña puesta al registrarse.
- **RN-01.** Los perfiles de Secretaría, Subgerencia financiera y Director de orquesta quedaron fuera del alcance por decisión de la fundación.
- **LAND-01.** La portada no sigue todas las reglas de estilo de la taste-skill (título en 3 líneas, degradados). Es solo estético y la portada ya está aprobada; se ajusta si la fundación lo pide.
- **UX-10.** Se corrigieron las tildes, el formato de números (1.234) y los textos con género en las pantallas. Los textos de teoría del contenido de demostración aún no llevan tildes ni «¿»: se corrigen al cargar el contenido real.
- **Accesibilidad.** Falta un recorrido con un lector de pantalla real (NVDA o TalkBack); axe y las pruebas de teclado pasan.

