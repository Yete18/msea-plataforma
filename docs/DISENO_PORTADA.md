# Portada de MSEA

**Idea.** El verde del sistema manda. La cabecera es verde bosque a todo lo ancho, con cinco líneas de pentagrama en verde claro, y la
foto principal (niñas y niños tocando el violín en el escenario) va grande, recortada con la silueta de un violín: mástil, cuerdas,
puente dorado y el contorno en verde claro. Debajo, la página cuenta lo que hace la fundación con fotos grandes y muestra la plataforma.

| Token | Valor | Uso |
|---|---|---|
| Verde bosque | `#315C3A` | Cabecera, títulos y pie |
| Verde MSEA | `#8CC63F` | Botón «Entrar a mi cuenta» y detalles |
| Verde claro | `#A8D858` | Contorno del violín, pentagrama, nombre MSEA |
| Menta | `#F2F8E9` | Franja de la plataforma y etiquetas de instrumentos |
| Marfil | `#F7F9F5` | Fondo de las secciones claras |
| Tinta / gris | `#26332B` / `#5C6A62` | Texto (contraste AA) |
| Dorado | `#E6B84A` | Solo el puente del violín y el foco del teclado |

- **Tipografía:** Baloo 2 (títulos) y Nunito (texto), las mismas de los paneles.
- **Secciones:** cabecera con el violín; «Lo que hace la fundación» (orquesta infantil y juvenil, clases de cuerdas, conciertos para la
  comunidad, valores a través de la música) en un mosaico de cuatro fotos grandes de tamaños distintos; «La práctica sigue en casa» con una
  captura real del afinador.
- **Movimiento:** solo la entrada del violín al cargar; se desactiva con «reducir movimiento».
- **Una sola llamada a la acción:** «Entrar a mi cuenta». Quien no tiene cuenta usa el enlace de texto de registro.

Archivos: `resources/views/index.blade.php` y `public/css/portada.css`. Para cambiar una foto del mosaico, reemplaza el `.webp` en
`public/assets/img/` (1200 px de ancho) y su texto `alt`. La foto del violín se cambia en el atributo `href` de `<image>`.
