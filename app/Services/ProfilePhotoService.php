<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fotos de perfil. Son fotos de menores: se guardan en el disco PRIVADO y solo se
 * sirven por /archivos/fotos/{idUsuario} a quien tiene relacion con la persona
 * (ella misma, la direccion, su profesor, su familia o sus contactos academicos).
 *
 * Las fotos antiguas que quedaron en el disco publico (storage/app/public/avatars)
 * se mueven al privado la primera vez que alguien autorizado las pide, o todas a
 * la vez con `php artisan msea:fotos-privadas`.
 */
class ProfilePhotoService
{
    private const MAX_IMAGE_BYTES = 2_000_000;

    public function __construct(private readonly ArchivoPrivadoService $archivos) {}

    /**
     * Guarda la foto si llega en base64 y devuelve su ruta privada. Si se indica
     * la foto anterior, se borra (antes quedaban archivos huerfanos).
     */
    public function storeIfBase64(?string $foto, ?string $anterior = null): ?string
    {
        if (! $foto || ! str_starts_with($foto, 'data:image')) {
            return $foto;
        }

        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $foto, $matches)) {
            throw ValidationException::withMessages(['foto' => 'La foto debe ser una imagen válida.']);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $contenido = base64_decode($matches[2], true);

        if ($contenido === false) {
            throw ValidationException::withMessages(['foto' => 'La foto debe ser una imagen válida.']);
        }

        if (strlen($contenido) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages(['foto' => 'La foto no puede superar 2 MB.']);
        }

        $imageInfo = @getimagesizefromstring($contenido);
        $extensionsByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        $realExtension = $extensionsByMime[$imageInfo['mime'] ?? ''] ?? null;
        if (! $realExtension || $realExtension !== $extension) {
            throw ValidationException::withMessages(['foto' => 'La foto debe ser una imagen PNG, JPG o WEBP válida.']);
        }

        $path = 'avatars/'.Str::uuid().'.'.$realExtension;
        $this->archivos->guardarContenido($path, $contenido);
        $this->eliminar($anterior);

        return $path;
    }

    /**
     * URL que usa la interfaz. Las fotos guardadas por el sistema se sirven por la
     * ruta autorizada; el parametro v cambia cuando cambia la foto (cache).
     */
    public function publicValue(?string $foto, ?int $idUsuario = null): ?string
    {
        if (! $foto || str_starts_with($foto, 'data:image')) {
            return $foto;
        }

        if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://')) {
            return $foto;
        }

        if ($idUsuario === null) {
            return null;
        }

        return url('/archivos/fotos/'.$idUsuario).'?v='.substr(sha1($foto), 0, 10);
    }

    /** Ruta privada lista para servir; mueve al privado una foto antigua del disco publico. */
    public function rutaPrivada(?string $foto): ?string
    {
        $path = $this->normalizar($foto);
        if ($path === null) {
            return null;
        }

        if (! $this->archivos->existe($path) && Storage::disk('public')->exists($path)) {
            $this->archivos->guardarContenido($path, (string) Storage::disk('public')->get($path));
            Storage::disk('public')->delete($path);
        }

        return $this->archivos->existe($path) ? $path : null;
    }

    public function eliminar(?string $foto): void
    {
        $path = $this->normalizar($foto);
        if ($path === null) {
            return;
        }

        $this->archivos->eliminar($path);
        Storage::disk('public')->delete($path);
    }

    /**
     * Quien puede ver la foto de $idDueno: la propia persona, la direccion, su
     * familia vinculada, su profesor (o sus alumnos si el dueno es profesor) y
     * sus contactos academicos de mensajeria.
     */
    public function puedeVer(int $idUsuario, string $rol, int $idDueno): bool
    {
        if ($idUsuario === $idDueno || $rol === 'director') {
            return true;
        }

        $estudianteDueno = DB::table('estudiantes')->where('id_usuario', $idDueno)->first();

        if ($rol === 'padre' && $estudianteDueno) {
            return app(TareaService::class)->padreVinculado($idUsuario, (int) $estudianteDueno->id_estudiante);
        }

        if ($rol === 'profesor' && $estudianteDueno) {
            return DB::table('profesores')->where('id_usuario', $idUsuario)->value('id_profesor') === $estudianteDueno->id_profesor;
        }

        return app(MensajeService::class)->puedeEscribir($idUsuario, $rol, $idDueno);
    }

    /** "/storage/avatars/x.png" (valor antiguo) o "avatars/x.png" -> "avatars/x.png". */
    private function normalizar(?string $foto): ?string
    {
        if (! $foto || str_starts_with($foto, 'data:') || str_starts_with($foto, 'http')) {
            return null;
        }

        $path = ltrim(preg_replace('#^/?storage/#', '', $foto) ?? '', '/');

        return preg_match('#^avatars/[0-9a-f-]{36}\.(png|jpg|webp)$#', $path) ? $path : null;
    }
}
