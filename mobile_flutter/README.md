# MSEA — aplicación móvil (Flutter)

Cliente móvil de MSEA. Consume la API `/api/mobile/*` del backend Laravel.

> **Estado:** compila con Flutter 3.47 (`flutter analyze` sin errores). El inicio de sesión con Google está pendiente de probar con un ID de cliente real.

## Qué hace

| Rol | Pantallas |
|---|---|
| Estudiante | Inicio (nivel, XP, racha, ranking, logros, tareas pendientes, XP de práctica del día), Tareas (ver y entregar con comentario), Práctica (inicio y fin medidos por el servidor), Avisos, Cuenta |
| Padre/madre | Inicio con cada hijo vinculado (nivel, racha, ranking, minutos, tareas y observaciones), Avisos, Cuenta |
| Profesor | Inicio (alumnos, tareas, entregas por revisar), Avisos, Cuenta |
| Director | Inicio (totales), Avisos, Cuenta |

En **Cuenta** se ven las sesiones abiertas en otros teléfonos. Desde ahí se puede cerrar una sesión concreta, la sesión actual o todas a la vez.

### Seguridad

- El token se guarda con `flutter_secure_storage`: Keychain en iOS y almacenamiento cifrado en Android. Nunca va a texto plano.
- La sesión se restaura al abrir la app. Si el servidor responde 401 (token vencido o revocado), se borra el token y se vuelve al login.
- La pantalla de login no trae credenciales precargadas.
- En **release** solo se acepta una URL `https://`. Si falta `MSEA_API_URL` o usa HTTP, la app muestra un error de configuración y no envía credenciales.
- Los errores de red (sin conexión, timeout de 20 s, TLS) y los 5xx muestran mensajes en español sin detalles internos.

### Limitaciones conocidas

- **Grabaciones:** la API ya acepta un archivo (`archivo`) en `POST /tareas/{id}/entregar`, pero esta versión de la app solo envía un comentario. Para adjuntar audio hace falta un selector de archivos o grabador como `file_picker` o `record`, que aún no se añadió. Mientras tanto, las grabaciones se suben desde la web.
- **Práctica:** la app no analiza el sonido del instrumento. La duración la calcula el servidor. El BPM opcional se guarda como dato declarado y no verificado.
- Calificar, gestionar usuarios, cursos y reportes solo está disponible en el panel web.

## Endpoints usados

```
POST   /api/mobile/login                     (5/min por correo+IP)
POST   /api/mobile/login/google              (id_token de Google)
GET    /api/mobile/me
GET    /api/mobile/tareas                    (estudiante)
POST   /api/mobile/tareas/{id}/entregar      (estudiante)
POST   /api/mobile/practicas                 (estudiante, inicia)
POST   /api/mobile/practicas/{id}/finalizar  (estudiante)
GET    /api/mobile/notificaciones
POST   /api/mobile/notificaciones/leidas
GET    /api/mobile/sesiones
DELETE /api/mobile/sesiones/{id}
POST   /api/mobile/logout
POST   /api/mobile/logout-todo
```

## Cómo verla en Chrome (lo más simple)

Necesitas dos terminales (por ejemplo, dos pestañas de la terminal de VS Code).

**Terminal 1: backend** (en la carpeta `msea`)

```bash
php artisan serve
```

Déjala abierta. Comprueba que funciona abriendo http://localhost:8000/up: debe aparecer una página verde.

**Terminal 2: app** (en la carpeta `msea/mobile_flutter`)

```bash
flutter pub get
flutter run -d chrome --web-port=5000
```

Se abre Chrome con la app. Entra, por ejemplo, con `estudiante@msea.test` / `estudiante123` (cuentas del seeder `MseaDemoSeeder`).

- En modo desarrollo la app busca el backend en `http://127.0.0.1:8000` (Chrome/Windows) o `http://10.0.2.2:8000` (emulador Android). No hace falta escribir la dirección.
- Si el backend está en otro lugar: agrega `--dart-define=MSEA_API_URL=http://otra-direccion:puerto`.
- Mientras `flutter run` está activo: `r` recarga los cambios, `R` reinicia la app y `q` la cierra.
- Para el emulador Android: ábrelo desde Android Studio y usa `flutter run` (sin `-d chrome`).

## Iniciar sesión con Google (opcional)

1. En https://console.cloud.google.com crea un proyecto y abre **APIs y servicios → Pantalla de consentimiento de OAuth** (tipo "Externo"; agrega tu correo como usuario de prueba).
2. En **Credenciales → Crear credenciales → ID de cliente de OAuth**:
   - Tipo **Aplicación web**. En "Orígenes de JavaScript autorizados" agrega `http://localhost:8000` (página web) y `http://localhost:5000` (app en Chrome). Copia el ID de cliente.
   - Para Android, crea además un ID de tipo **Android** con el paquete `pe.msea.msea_mobile` y la huella SHA-1 que muestra `cd android` y luego `.\gradlew signingReport`. No necesitas copiar ese ID.
3. Backend: en el `.env` de `msea` pon `GOOGLE_CLIENT_ID=<ID web>` y ejecuta `php artisan config:clear`. El botón aparece en http://localhost:8000/login (abre la web con `localhost`, no con `127.0.0.1`).
4. App: agrega el mismo ID web al ejecutar:

```bash
flutter run -d chrome --web-port=5000 --dart-define=GOOGLE_CLIENT_ID=<ID web>
```

Solo pueden entrar con Google las personas que **ya tienen cuenta en MSEA con ese mismo correo** (el rol lo asigna la dirección). La primera vez la cuenta queda vinculada a ese Google.

### Producción

```bash
flutter build apk --release --dart-define=MSEA_API_URL=https://tu-dominio-msea
flutter build ipa --release --dart-define=MSEA_API_URL=https://tu-dominio-msea
```

Requisitos para publicar:

- Configurar la firma (keystore de Android y certificados de iOS).
- Usar un nombre e icono definitivos.
- Probar en dispositivos físicos.

### Fuentes

El tema usa `Nunito`. Para empaquetarla, descarga los `.ttf` (licencia OFL) en `assets/fonts/` y declárala en `pubspec.yaml`. Si no lo haces, se usa la fuente del sistema.
