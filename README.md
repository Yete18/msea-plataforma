# MSEA — Movimiento Sinfónico de El Alto

Plataforma educativa para estudiantes de cuerdas (violín, viola, violonchelo y contrabajo). Tiene paneles para el director, los profesores, los estudiantes y las familias, gamificación (XP, niveles, rachas, logros y ranking), tareas con grabaciones privadas, cursos con lecciones y una API para la app móvil.

## Inicio rápido (desarrollo)

```bash
composer install
cp .env.example .env
php artisan key:generate
# configurar DB_* en .env (PostgreSQL)
php artisan migrate
php artisan db:seed                          # catálogos (y, fuera de producción, datos de prueba)
php artisan storage:link
php artisan serve
```

## Iniciar sesión con Google (opcional)

Configura `GOOGLE_CLIENT_ID` en `.env` (ID de cliente OAuth tipo "Aplicación web" de Google Cloud, con `http://localhost:8000` como origen autorizado) y ejecuta `php artisan migrate` y `php artisan config:clear`. Los pasos completos están en [mobile_flutter/README.md](mobile_flutter/README.md#iniciar-sesión-con-google-opcional).

## Documentación

| Documento | Contenido |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Estructura, servicios, autenticación, gamificación y archivos |
| [SECURITY.md](SECURITY.md) | Controles de seguridad y cómo se prueban |
| [TESTING.md](TESTING.md) | Cómo ejecutar las pruebas (SQLite, PostgreSQL y E2E) y últimos resultados |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Producción, actualización y copias de seguridad |
| [TECH_DEBT.md](TECH_DEBT.md) | Pendientes conocidos |
| [RESUMEN_TECNICO_SISTEMA_MSEA.txt](RESUMEN_TECNICO_SISTEMA_MSEA.txt) | Explicación sencilla del sistema |
| [docs/INFORME_AUDITORIA_INTEGRAL_2026-10-17.md](docs/INFORME_AUDITORIA_INTEGRAL_2026-10-17.md) | Auditoría integral y rediseño de octubre de 2026 (fases A a F) |
| [mobile_flutter/README.md](mobile_flutter/README.md) | Aplicación móvil |

Construido con [Laravel](https://laravel.com) (licencia MIT).

## Autor

- **Proyecto:** Plataforma educativa interactiva y gamificada MSEA
- **Estudiante:** Yeray Eleazar Tintaya Espejo
- **Materia:** Proyecto Integrador Intermedio II - UNIFRANZ

## Autor

- **Proyecto:** Plataforma educativa interactiva y gamificada MSEA
- **Estudiante:** Yeray Eleazar Tintaya Espejo
- **Materia:** Proyecto Integrador Intermedio II - UNIFRANZ
