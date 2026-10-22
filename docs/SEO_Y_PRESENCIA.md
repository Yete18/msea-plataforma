# Presencia en Google, analítica y contacto

Esta guía cubre lo que el sitio ya trae hecho y lo que **debe hacer una persona de la fundación**, porque depende de cuentas y
datos reales que el sistema no puede inventar.

## Lo que ya está en el sitio

| Tema | Qué se hizo | Dónde |
|---|---|---|
| Aviso legal, privacidad y cookies | Tres páginas públicas con los datos reales del sistema | `/aviso-legal`, `/politica-de-privacidad`, `/politica-de-cookies` |
| Aviso de cookies | Aviso informativo; con analítica activa pide permiso (Aceptar / Solo necesarias) y se puede reabrir | `partials/cookies`, `public/js/cookies.js` |
| HTTPS | Redirección automática en producción | `app/Http/Middleware/ForzarHttps.php` |
| Títulos y descripciones | Único por página; las privadas llevan `noindex` | `partials/seo` |
| Datos estructurados | Organización educativa + sitio web (JSON-LD) y migas de pan en las páginas legales | `app/Support/DatosEstructurados.php` |
| Sitemap y robots | Se generan solos con la dirección real del sitio | `/sitemap.xml`, `/robots.txt` |
| Favicon e iconos | Hechos con el emblema del logo (pestaña, celular, manifiesto) | `public/favicon*.png`, `public/site.webmanifest` |
| Imágenes | WebP comprimido (60–160 KB en vez de 350–680 KB) con texto alternativo descriptivo | `public/assets/img/*.webp` |
| Velocidad | Sin scripts externos; iconos en un sprite SVG que se guarda en caché; gzip y caché | `public/assets/img/iconos.svg`, `.htaccess` |
| Contraste y móvil | Revisado con axe-core en 8 páginas, escritorio y móvil: 0 errores | `tests/e2e/sitio-publico.cjs` |
| 404 y otros errores | Páginas propias para 404, 403, 429, 500 y 503 | `resources/views/errors/` |
| Formularios | Campo trampa invisible + tiempo mínimo + límite de intentos, sin captcha de terceros | `ProteccionSpam.php` |
| WhatsApp | Botón visible en portada y textos legales (solo si se configura el número) | `partials/whatsapp` |
| Una sola llamada a la acción | La portada tiene un único botón: «Entrar a mi cuenta» | `index.blade.php` |

## Lo que falta y depende de la fundación

1. **Completar el `.env`** (ver `.env.example`, bloque «Sitio público»): correo, teléfono, dirección, NIT (si tiene), redes y WhatsApp.
   Los textos legales muestran esos datos; sin ellos indican contactar a la dirección por medio del profesor.
2. **Revisión legal.** Los textos legales son una base redactada según lo que hace el sistema. Conviene que un abogado de la fundación
   los revise, sobre todo el plazo de conservación de datos y el trato de datos de menores.
3. **Fotos de niñas y niños.** Las imágenes de la portada muestran personas menores de edad. Verifica que la fundación cuente con la
   autorización de madres, padres o tutores para publicarlas.
4. **Ficha de Google (Google Business Profile).** No se puede crear desde el código: la hace una persona con la cuenta de la fundación.
   - Entra a <https://business.google.com> con un correo institucional y crea el perfil como «Escuela de música» u «Organización sin fines de lucro».
   - Usa **exactamente** el mismo nombre, dirección y teléfono que pusiste en el `.env` (Google compara esos datos con el sitio).
   - Verifica el perfil (Google envía un código por llamada, mensaje o video, según la zona).
   - Agrega horario, fotos propias y el enlace del sitio; luego pega la URL del perfil en `MSEA_FICHA_GOOGLE_URL` para que
     aparezca en los datos estructurados.
5. **Google Search Console.** Entra a <https://search.google.com/search-console>, agrega el dominio, verifícalo (registro DNS) y envía
   `https://TU-DOMINIO/sitemap.xml`. Así Google conoce la portada y los textos legales.
6. **Analítica.** Está apagada por defecto. Para activarla elige una:
   - *Plausible* (recomendada: sin cookies): crea el sitio en plausible.io y pon `MSEA_ANALITICA_PLAUSIBLE=tu-dominio.org`.
   - *Google Analytics 4*: crea una propiedad, copia el ID `G-…` y ponlo en `MSEA_ANALITICA_GA4`.
   Solo carga en páginas públicas y **después de que la persona acepte**; nunca dentro de los paneles. Se configuró sin señales
   de publicidad. Al activarla, la política de cookies se actualiza sola.
7. **WhatsApp.** Pon el número en `MSEA_WHATSAPP` (solo dígitos con código de país, p. ej. `5917XXXXXXX`). Mientras esté vacío el botón no aparece.

## Notas técnicas

- Los iconos de las páginas de acceso salen del mismo sprite que los paneles (`public/assets/img/iconos.svg`). Para agregar uno, añade su `<symbol id="i-nombre">` al sprite; la prueba `FaseEAccesibilidadTest` avisa si una vista usa un icono que no existe.
- Las imágenes `.jpg`/`.png` originales se conservaron como respaldo para navegadores muy antiguos; el sitio usa las `.webp`.
- `public/robots.txt` se reemplazó por una ruta dinámica para incluir la dirección real en `Sitemap:`.
