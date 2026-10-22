# Despliegue de MSEA

Esta es una guía para poner MSEA en un servidor propio. **Haz siempre una copia de seguridad antes de migrar.**

## Requisitos

- PHP 8.2 o superior, con estas extensiones: `pdo_pgsql`, `mbstring`, `fileinfo` (obligatoria para validar archivos), `openssl`, `gd` (fotos) e `intl`.
- PostgreSQL 14 o superior.
- Composer 2.
- Servidor web (Nginx o Apache) con HTTPS.
- Un proceso supervisor (systemd o supervisord) si se usa la cola.

## Variables de entorno de producción

Parte de `.env.example` y ajusta como mínimo:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio
APP_KEY=                      # php artisan key:generate
APP_LOCALE=es
APP_TIMEZONE=America/La_Paz   # define cuándo empieza el día para rachas, metas y topes
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=...  DB_DATABASE=...  DB_USERNAME=...  DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true

MAIL_MAILER=smtp
MAIL_HOST=...  MAIL_PORT=587  MAIL_USERNAME=...  MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-responder@tu-dominio
MAIL_FROM_NAME="MSEA"

MSEA_PERMITIR_DEMO=false
MSEA_CORREO_EN_COLA=true      # opcional; requiere el worker de la cola
```

No subas `.env` al repositorio ni lo compartas.

## Primer despliegue

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # solo catálogos (instrumentos, niveles, logros); en producción no crea cuentas
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Para crear la primera cuenta de director sin los datos de demostración, usa `php artisan tinker` o un seeder propio. `MseaDemoSeeder` **se niega a ejecutarse en producción**, porque crea cuentas con contraseñas conocidas.

## Actualizar una instalación existente (esta versión)

1. Haz una copia de seguridad: `pg_dump -Fc -d MSEA > msea_$(date +%F).dump` y copia `storage/app`.
2. Despliega el código y ejecuta `composer install --no-dev`.
3. Ejecuta `php artisan migrate --force`. Aplica `2026_10_10_000001_msea_seguridad_y_gamificacion`, que:
   - Solo añade columnas, tablas, índices y la vista. No borra datos.
   - Normaliza los correos a minúsculas. Si hay duplicados que solo difieren en mayúsculas, **no** crea el índice único. Revisa esos duplicados con:
     `SELECT lower(correo), count(*) FROM usuarios GROUP BY 1 HAVING count(*) > 1;`
   - Crea un saldo inicial en `xp_movimientos` con la XP que ya tenía cada estudiante, para no perder el progreso.
   - `2026_10_11_000001_msea_habitos_estudiante` añade `estudiantes.meta_diaria_minutos` (meta diaria de práctica).
4. Ejecuta `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Si los catálogos tenían instrumentos duplicados («Chelo» y «Violonchelo», «Bajo» y «Contrabajo»), el director debe unificarlos desde *Catálogos*. La migración no los fusiona automáticamente.

6. Ejecuta `php artisan msea:fotos-privadas` una sola vez. Mueve las fotos de perfil antiguas de `storage/app/public/avatars` al disco privado. Desde esta versión las fotos se sirven por `/archivos/fotos/{id}` y solo las ven el dueño, su profesor, sus padres vinculados, el director y sus contactos de mensajería.
7. Ejecuta `php artisan db:seed --class=CatalogosSeeder --force`. Añade los instrumentos, niveles y logros que falten; no crea
   cuentas ni modifica los logros que el director ya editó.
8. Las migraciones `2026_10_17_*` cambian varias claves foráneas a `RESTRICT` (ya no se borran en cascada tareas, entregas ni ejercicios al eliminar un profesor o un estudiante) y añaden `usuarios.origen` y `usuarios.correo_verificado_at`. No borran datos. Además:
   - Convierten a hash las contraseñas antiguas que estaban en texto plano, con el mismo valor (nadie pierde el acceso).
   - Crean índices en las claves foráneas (con `CONCURRENTLY`, sin bloquear tablas).
   - Crean reglas `CHECK` (estados válidos, nota de 0 a 100, XP no negativo) y un nombre único por elenco. Si alguna fila
     antigua no cumple una regla, no se toca: la regla se crea como `NOT VALID` y el log indica qué revisar.

9. Configura el cron de `schedule:run` y el worker del digitalizador (secciones *Tareas programadas* y *Cola y correo*).
10. Si quedó un archivo `public/robots.txt` de una versión anterior, bórralo: el sitio genera `/robots.txt` con la dirección real y el archivo fijo lo taparía.
11. Comprueba que el esquema de producción tenga las mismas restricciones e índices que el de pruebas (BD-11). En PostgreSQL:
    ```sql
    -- Restricciones CHECK y claves foráneas de MSEA (deben salir todas; convalidated = false indica filas antiguas por revisar)
    SELECT conrelid::regclass AS tabla, conname, contype, convalidated
    FROM pg_constraint WHERE connamespace = 'public'::regnamespace AND contype IN ('c', 'f') ORDER BY 1, 2;
    -- Índices creados por la fase B (empiezan por idx_) y el único de correo
    SELECT tablename, indexname FROM pg_indexes WHERE schemaname = 'public' AND (indexname LIKE 'idx_%' OR indexname LIKE '%correo%') ORDER BY 1, 2;
    ```
    Compara el resultado con el de la base de pruebas (`msea_testing` tras `php artisan test -c phpunit.pgsql.xml`). Una diferencia indica una migración que no se aplicó o una regla `NOT VALID` pendiente.

### Revertir un despliegue

**No uses `migrate:rollback` en producción.** Varios `down()` eliminan columnas o tablas con datos reales, así que en producción están bloqueados: fallan con un mensaje salvo que se defina `MSEA_PERMITIR_ROLLBACK=true` a propósito.

Para volver atrás:

1. Pon el sitio en mantenimiento: `php artisan down`.
2. Vuelve al código anterior (la etiqueta de git del despliegue previo).
3. Restaura la copia de la base de datos: `pg_restore --clean --if-exists -d MSEA msea_AAAA-MM-DD.dump`.
4. Restaura `storage/app` desde la copia del mismo día.
5. `php artisan config:cache && php artisan up`.

## Copias de seguridad

- **Diaria**, por cron del servidor, fuera de la carpeta de la aplicación:
  `pg_dump -Fc -d MSEA > /respaldos/msea_$(date +%F).dump`
  y `tar czf /respaldos/storage_$(date +%F).tgz storage/app/private`.
- Conserva al menos 14 copias diarias y una mensual. Guarda una copia fuera del servidor.
- **Simulacro de restauración** una vez al mes: restaura la última copia en una base de prueba (`createdb msea_prueba && pg_restore -d msea_prueba ...`) y comprueba que el director puede entrar. Una copia que nunca se restauró no está comprobada.
- Las copias contienen datos personales de menores: protégelas con los mismos permisos que la base de datos.

## Archivos

- Las grabaciones y el material se guardan en `storage/app/private`. **Inclúyelos en las copias de seguridad.** No deben quedar expuestos por el servidor web.
- Los límites de subida de PHP deben ser mayores que los de la aplicación: `upload_max_filesize = 25M` y `post_max_size = 30M`.
- En Nginx: `client_max_body_size 30m;`.

## Cola y correo

Si `MSEA_CORREO_EN_COLA=true`:

```ini
[program:msea-queue]
command=php /ruta/msea/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
```

El digitalizador va por la cola en producción (`MSEA_OMR_EN_COLA`, verdadero por defecto) y usa su propia conexión,
`database-largo`, porque una partitura puede tardar hasta una hora. Necesita su propio worker:

```ini
[program:msea-omr]
command=php /ruta/msea/artisan queue:work database-largo --queue=omr --timeout=3600 --tries=1
autostart=true
autorestart=true
stopwaitsecs=3700
```

## Tareas programadas

Añade esta línea al cron del usuario del servidor web:

```
* * * * * cd /ruta/msea && php artisan schedule:run >> /dev/null 2>&1
```

Cada día a las 03:15 ejecuta `msea:limpiar`, que borra los tokens móviles vencidos hace más de 7 días, los códigos de
recuperación de más de 7 días y los avisos leídos de más de 180 días, y cierra las prácticas abandonadas. No toca entregas,
XP, mensajes ni la bitácora. Compruébalo con `php artisan schedule:list`.

Los logs rotan a diario (`LOG_STACK=daily`) y se conservan `LOG_DAILY_DAYS` días (14 por defecto).

## App móvil

Compila con la URL HTTPS de producción (detalles en `mobile_flutter/README.md`):

```bash
flutter build apk --release --dart-define=MSEA_API_URL=https://tu-dominio
```

## Comprobación tras el despliegue

- `/login` carga en HTTPS y la respuesta incluye la cabecera `Content-Security-Policy` (con `style-src 'self'`, sin `'unsafe-inline'`).
- Los iconos se ven en el login y en los paneles (sprite `assets/img/iconos.svg`). Si el sitio sirve los recursos desde otro dominio (`ASSET_URL`), el sprite debe quedar en el mismo dominio que las páginas.
- El login entra con solo correo y contraseña, cada rol a su panel.
- El login falla con el mensaje genérico ante credenciales incorrectas, y tras 5 intentos responde 429.
- El correo de recuperación llega de verdad.
- `APP_DEBUG=false`: un error 500 no muestra la traza.
- `/storage/` no expone grabaciones ni fotos de perfil: ambas están en el disco privado y se sirven con sesión.

## Sitio público: HTTPS, SEO, WhatsApp y analítica

**HTTPS.** En producción (`APP_ENV=production`) MSEA redirige todo a HTTPS (301 para lectura, 308 para envíos). Si hay un proxy
(Nginx, Cloudflare, balanceador) delante de PHP, declara su IP concreta en `MSEA_PROXIES` (Nginx en el mismo servidor: `127.0.0.1`);
sin eso Laravel no sabe que la petición original era segura y se produce un bucle de redirecciones. **No uses `*`**: si PHP queda
accesible sin pasar por el proxy, cualquiera podría falsear su IP y saltarse el límite de intentos de inicio de sesión.
`/up` queda fuera de la redirección.

**Dominios aceptados.** En producción solo se responden peticiones al dominio de `APP_URL` y sus subdominios; para otro dominio
(por ejemplo, el antiguo) añádelo a `MSEA_HOSTS`. Una petición con otro `Host` responde 400.

**Valores seguros.** En producción la cookie de sesión es `secure` por defecto (solo HTTPS). El sitio no arranca con
`APP_DEBUG=true`, y con `MAIL_MAILER=log` no se envían códigos de recuperación (quedarían escritos en el log): configura SMTP.

**Compresión y caché.** `public/.htaccess` activa gzip y caché de 30 días para imágenes, iconos y tipografías. En Nginx:

```
gzip on; gzip_types text/css application/javascript application/json image/svg+xml application/xml;
location ~* \.(webp|jpe?g|png|ico|svg|woff2)$ { expires 30d; add_header Cache-Control "public"; }
```

**Datos de la fundación.** Los textos legales, los datos estructurados para Google y el botón de WhatsApp leen sus datos del `.env`
(ver el bloque «Sitio público» de `.env.example`). Lo que quede vacío no se muestra: el botón de WhatsApp no aparece sin
`MSEA_WHATSAPP`, y la analítica no se carga sin `MSEA_ANALITICA_GA4` o `MSEA_ANALITICA_PLAUSIBLE`.

**Después de desplegar:** abre `/sitemap.xml` y `/robots.txt`, y sigue `docs/SEO_Y_PRESENCIA.md` (Google Search Console y ficha de Google).
