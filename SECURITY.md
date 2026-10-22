# Seguridad en MSEA

Este documento resume los controles de seguridad del sistema y cómo se verifican. Las pruebas se encuentran en `tests/Feature/Seguridad/` y `tests/e2e/xss.cjs`.

## Autenticación y sesiones

| Control | Dónde | Prueba |
|---|---|---|
| Login con mensaje único («Correo electrónico o contraseña incorrectos.») y tiempo constante (se hace un hash aunque el correo no exista) | `AuthService::autenticar` | `AutenticacionTest` |
| El hash almacenado no sirve como contraseña: `Hash::check` solo se aplica cuando el valor guardado parece un hash | `AuthService::contrasenaValida` | `AutenticacionTest` |
| Las contraseñas antiguas en texto plano se convirtieron a hash con una migración; el login ya no acepta texto plano | migración `2026_10_17_000003`, `AuthService` | `FaseBDatosTest`, `AuthServiceTest` |
| El rol no se elige al entrar: sale de la cuenta, y un `rol` enviado por un formulario o una app antiguos se ignora | `LoginRequest`, `AuthController`, `MobileController` | `FaseDAcademicoTest`, `ApiMovilTest` |
| Límites de intentos: login web y móvil (5/min por correo+IP y 20/min por IP), recuperación (3 cada 10 min por correo), código (10 cada 10 min) y acciones sensibles (10/min) | `AppServiceProvider` | `AutenticacionTest`, `ApiMovilTest` |
| ID de sesión regenerado al entrar e invalidado al salir | `AuthController` | `AuthFlowTest` |
| Cuenta desactivada: se expulsa en la siguiente petición | `AuthenticateSession`, `usuarios.activo` | `GestionDirectorTest` |
| Cambio de rol en la base de datos: la sesión antigua deja de valer | `AuthenticateSession` | `AutenticacionTest` |
| Cambiar la contraseña cierra las demás sesiones web y revoca los tokens móviles | huella `huella_credencial`, `CuentaSeguridadService` | `AutenticacionTest` |
| Cambiar el correo o la contraseña exige la contraseña actual | Form Requests de perfil | `DashboardEstudianteTest` y otras |
| Correos normalizados a minúsculas, con índice único `lower(correo)` cuando no hay duplicados previos | trait `NormalizaCorreo`, migración | `RecuperacionYCorreoTest` |

## Inicio de sesión con Google

- La página (`/auth/google`) y la app (`/api/mobile/login/google`) envían el *ID token* que entrega Google. `GoogleAuthService` lo valida con Google (`oauth2.googleapis.com/tokeninfo`) y exige: emitido para nuestro `GOOGLE_CLIENT_ID`, emisor `accounts.google.com`, vigente y con correo verificado.
- Google **no crea cuentas**: solo entra quien ya tiene cuenta activa en MSEA con ese correo. La primera vez se guarda el identificador de Google (`usuarios.google_sub`); un correo ya vinculado a otra cuenta de Google se rechaza.
- Mismos límites de intentos que el login normal y registro en `auditoria` (`login_google`, `login_google_fallido`).
- La CSP solo permite `accounts.google.com/gsi/*` si `GOOGLE_CLIENT_ID` está configurado. `Cross-Origin-Opener-Policy` es `same-origin-allow-popups` para la ventana de Google.

## Recuperación de contraseña

- El código es de 6 dígitos, se guarda con hash y caduca en 15 minutos.
- Cada código admite un máximo de 5 intentos.
- Si el correo no existe, la respuesta es idéntica a la de un correo existente.
- El correo se envía con el Mailable `CodigoRecuperacionMail`. Si falla, el error se registra sin exponerse.
- Al restablecer la contraseña se revocan los tokens móviles.

## API móvil

- Usa un token Bearer propio. Solo se guarda su hash SHA-256, caduca a los 30 días, admite un máximo de 5 por usuario y se puede revocar uno a uno o todos.
- `auth.mobile:estudiante` restringe las rutas exclusivas del estudiante.
- Las prácticas no aceptan una duración enviada por el cliente.

## Autorización de recursos (IDOR)

- `TareaService::tareasVisiblesPara` decide qué tareas ve cada estudiante: solo las suyas o las de su elenco.
- Entregar, ver material o descargar una grabación pasa por `TareaService`, que comprueba la propiedad: el estudiante dueño, el profesor de la tarea, el padre vinculado o el director.
- Los profesores solo califican las tareas que crearon, y dentro de un elenco solo ven y califican a sus propios alumnos (decisión de la fundación, SEC-01).
- El estudiante no puede cambiar su CI, dirección ni fecha de nacimiento; solo la dirección los corrige.
- Los mensajes solo se envían entre personas vinculadas (`MensajeService`).

## Archivos

- Se guardan en el disco privado con un nombre UUID. El nombre original solo se usa como metadato.
- El MIME se detecta con `finfo` sobre el contenido real y se valida contra listas permitidas en `config/msea.php`.
- Límites de tamaño: 20 MB para audio y 15 MB para material.
- Las respuestas llevan `X-Content-Type-Options: nosniff` y el MIME detectado al subir el archivo. Se usa `Content-Disposition: inline` para reproducir audio en el reproductor y `attachment` al pulsar «Descargar». Nunca se sirve como `text/html`.

## Sesión, flecha atrás y error 419

- Las páginas HTML y las respuestas con sesión se envían con `Cache-Control: no-store`: al volver con la flecha del navegador tras cerrar sesión, el servidor redirige al login en lugar de mostrar un panel guardado.
- Con sesión abierta, `/login` y `/registro` redirigen al panel (middleware `invitado`).
- Un token CSRF vencido ya no muestra la página «419 Page Expired»: al cerrar sesión se cierra igual; en formularios se vuelve a la página anterior con un aviso y los datos escritos (nunca las contraseñas); en peticiones JSON, `msea-ui.js` pide un token nuevo a `/csrf-token` y reintenta una sola vez.
- Con el panel abierto, el token se renueva cada 15 minutos mientras la pestaña está visible.

## XSS y cabeceras

- Todo el HTML dinámico se escapa con `MSEA_UI.esc`, y las URLs pasan por `safeUrl`. En Blade se usa `{{ }}`.
- La prueba E2E `tests/e2e/xss.cjs` inyecta cargas maliciosas en nombres, tareas, mensajes y comunicados. Recorre los cuatro paneles con la CSP desactivada y verifica que ninguna se ejecute.
- Se añade la cabecera CSP con nonce para los scripts, `style-src 'self'` (sin `'unsafe-inline'`: no hay atributos `style` ni `<style>` en las vistas), `object-src 'none'`, `frame-ancestors 'self'`, `base-uri 'self'` y `form-action 'self'`. `FaseEAccesibilidadTest` comprueba que ninguna página tenga atributos `style`.
- También se envían `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` (micrófono solo para el propio sitio) y `Cross-Origin-Opener-Policy`. HSTS se añade solo en peticiones HTTPS.

## Exportaciones CSV

`CsvExporter` antepone `'` a las celdas que empiezan por `=`, `+`, `-`, `@`, tabulador o retorno de carro. Así se evita la inyección de fórmulas al abrir el archivo en Excel o LibreOffice (`CsvYCabecerasTest`).

## Auditoría

La tabla `auditoria` registra:

- Inicios de sesión correctos y fallidos (el correo se guarda enmascarado).
- Cambios de contraseña y de correo.
- Revocación de sesiones.
- Altas, bajas y cambios de rol.
- Calificaciones y exportaciones.

`AuditLogger` elimina las claves `contrasena`, `password`, `token`, `codigo` y similares antes de guardar. El director consulta la auditoría en `/dashboard-admin/auditoria`.

## Riesgos aceptados y pendientes

- El cambio de correo no envía un enlace de verificación al correo nuevo.
- No hay autenticación de dos factores.

## Cómo reportar una vulnerabilidad

Escribe a la dirección de MSEA. No publiques el detalle hasta que esté corregido.
