<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos privados (grabaciones de entregas y materiales de tareas).
 *
 * - Se guardan en el disco privado (storage/app/private), nunca en public/.
 * - El nombre en disco es un UUID; la extension sale del tipo MIME REAL
 *   detectado por el servidor, no del nombre que manda el usuario.
 * - Solo se sirven a traves de ArchivoController, que verifica permisos.
 */
class ArchivoPrivadoService
{
    private const EXTENSION_POR_MIME = [
        'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav', 'audio/vnd.wave' => 'wav',
        'audio/ogg' => 'ogg', 'application/ogg' => 'ogg',
        'audio/webm' => 'webm', 'video/webm' => 'webm',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'video/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/x-hx-aac-adts' => 'aac',
        'application/pdf' => 'pdf',
        'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
    ];

    private const AUDIO = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'];

    private const MATERIAL = ['pdf', 'png', 'jpg', 'webp', 'mp3', 'wav', 'ogg', 'm4a'];

    public function guardarAudioEntrega(UploadedFile $archivo): array
    {
        return $this->guardar($archivo, 'entregas', self::AUDIO, 'La grabación debe ser un archivo de audio válido (mp3, wav, ogg, webm o m4a).');
    }

    public function guardarMaterial(UploadedFile $archivo): array
    {
        return $this->guardar($archivo, 'materiales', self::MATERIAL, 'El material debe ser PDF, imagen o audio.');
    }

    /** PDF de un método para el digitalizador (solo el director). */
    public function guardarPdfMetodo(UploadedFile $archivo): array
    {
        return $this->guardar($archivo, 'metodos', ['pdf'], 'El método debe ser un archivo PDF.');
    }

    /** Ruta absoluta de un archivo privado propio (para programas externos como el OMR). */
    public function rutaAbsoluta(string $path): string
    {
        abort_unless($this->esRutaPropia($path), 404);

        return Storage::disk($this->disco())->path($path);
    }

    /** Guarda contenido generado por el sistema (imágenes de página, MusicXML). */
    public function guardarContenido(string $path, string $contenido): void
    {
        abort_unless($this->esRutaPropia($path), 500);
        Storage::disk($this->disco())->put($path, $contenido);
    }

    public function existe(string $path): bool
    {
        return $this->esRutaPropia($path) && Storage::disk($this->disco())->exists($path);
    }

    public function eliminar(?string $path): void
    {
        if ($path && $this->esRutaPropia($path)) {
            Storage::disk($this->disco())->delete($path);
        }
    }

    public function respuesta(string $path, string $nombre, ?string $mime, bool $descargar = false): StreamedResponse
    {
        abort_unless($this->esRutaPropia($path) && Storage::disk($this->disco())->exists($path), 404);

        $headers = [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ];

        $disposicion = $descargar ? 'attachment' : 'inline';

        return Storage::disk($this->disco())->response($path, $this->nombreSeguro($nombre), $headers, $disposicion);
    }

    private function guardar(UploadedFile $archivo, string $carpeta, array $permitidas, string $mensaje): array
    {
        if (! $archivo->isValid()) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo recibir el archivo. Intenta de nuevo.']);
        }

        // MIME leido del CONTENIDO del archivo (firma), nunca de la extension ni del navegador.
        $mimeReal = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $archivo->getRealPath());
        $extension = self::EXTENSION_POR_MIME[$mimeReal] ?? null;

        if (! $extension || ! in_array($extension, $permitidas, true)) {
            throw ValidationException::withMessages(['archivo' => $mensaje]);
        }

        $ruta = $carpeta.'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk($this->disco())->putFileAs(dirname($ruta), $archivo, basename($ruta));

        return [
            'path' => $ruta,
            'nombre' => $this->nombreSeguro($archivo->getClientOriginalName(), $extension),
            'mime' => $mimeReal,
            'tamano' => (int) $archivo->getSize(),
        ];
    }

    /** Nombre solo para mostrar/descargar: sin rutas ni caracteres de control. */
    private function nombreSeguro(string $original, ?string $extension = null): string
    {
        $base = pathinfo(str_replace(['\\', '/'], '_', $original), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\pL\pN _.-]+/u', '_', $base) ?? '', ' ._') ?: 'archivo';
        $base = mb_substr($base, 0, 100);
        $extension ??= strtolower(pathinfo($original, PATHINFO_EXTENSION));

        return $extension ? $base.'.'.$extension : $base;
    }

    private function esRutaPropia(string $path): bool
    {
        return (bool) preg_match('#^(entregas|materiales|metodos)/\d{4}/\d{2}/[0-9a-f-]{36}\.[a-z0-9]{2,5}$#', $path)
            || (bool) preg_match('#^digitalizacion/\d+/pagina-\d+\.(png|xml)$#', $path)
            || (bool) preg_match('#^avatars/[0-9a-f-]{36}\.(png|jpg|webp)$#', $path);
    }

    private function disco(): string
    {
        return (string) config('msea.archivos.disco', 'local');
    }
}
