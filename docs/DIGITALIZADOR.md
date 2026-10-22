# Digitalizador de métodos (PDF → biblioteca)

El director sube un método en PDF (por ejemplo, un libro de Ševčík) y elige **solo las páginas** que quiere digitalizar (`4, 6` o `4-6`). Cada página pasa por el reconocimiento óptico de música (OMR), se revisa junto a la imagen original y cada ejercicio aprobado queda en la **biblioteca técnica** («Métodos digitalizados») para todos los profesores.

## Flujo

1. **Panel del director → Digitalizador de métodos.** Subir el PDF, el título, el instrumento y las páginas. Hay que confirmar que el método es de dominio público o que la fundación tiene permiso.
2. **Reconocimiento.** MSEA llama a Audiveris página por página (`-sheets N`). Lee el MusicXML que produce y separa los ejercicios por las dobles barras. Guarda la imagen de la página para la revisión.
3. **Revisión.** A la izquierda está la página original; a la derecha, cada ejercicio en texto y en el pentagrama. Se corrige el texto (`Do#5:c`, `Re5:n.`, `-:c`, `/2` para el dedo) y se ve el resultado al instante. Si la duración no completa compases, aparece un aviso: suele indicar una figura mal leída.
4. **Publicar.** El ejercicio queda en la biblioteca con su origen (método y página). Los profesores lo asignan desde «Nueva tarea → Técnica o método», en modo afinación (nota por nota) o velocidad (al tempo, subiendo hasta la meta).

Nada se publica sin revisión, porque ningún OMR es perfecto. Con un PDF de prueba generado por computadora, Audiveris acertó la mayoría de las notas, pero algunas corcheas salieron como negras o faltaron.

## Instalar el reconocimiento automático (Windows)

Sin Audiveris el digitalizador funciona igual: las páginas quedan para escribir las notas a mano mirando el PDF.

1. Descargar e instalar **Audiveris 5.7.0** (libre, licencia AGPL): `Audiveris-5.7.0-windowsConsole-x86_64.msi`, desde la página de versiones de Audiveris en GitHub. Trae su propio Java.
2. Buscar el ejecutable instalado, normalmente `C:\Program Files\Audiveris\Audiveris.exe`.
3. En el `.env` de MSEA:

   ```
   MSEA_OMR_AUDIVERIS="C:\Program Files\Audiveris\Audiveris.exe"
   ```

4. `php artisan config:clear`.

En Linux: instalar el `.deb` de Audiveris y usar `MSEA_OMR_AUDIVERIS=/opt/audiveris/bin/Audiveris`.

### Opciones

| Variable | Valor por defecto | Uso |
|---|---|---|
| `MSEA_OMR_AUDIVERIS` | vacío | Ruta del ejecutable de Audiveris |
| `MSEA_OMR_SEGUNDOS_PAGINA` | 300 | Tiempo máximo por página |
| `MSEA_OMR_EN_COLA` | false | `true` procesa en segundo plano (requiere `php artisan queue:work`); recomendado para muchas páginas |
| `MSEA_OMR_PDF_MAX_KB` | 51200 | Tamaño máximo del PDF (50 MB). En XAMPP revisa también `upload_max_filesize` y `post_max_size` en `php.ini` |

## Derechos de autor

Digitaliza solo métodos de dominio público o con permiso. Ojo con las ediciones modernas: una edición reciente puede tener derechos propios aunque la obra original sea libre.
