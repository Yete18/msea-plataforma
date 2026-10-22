<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fabrica minima de datos MSEA para pruebas (sin factories de Eloquent porque
 * las tablas usan claves propias como id_usuario, id_estudiante...).
 */
trait CreaDatosMsea
{
    private int $secuenciaCorreo = 0;

    protected function sembrarCatalogos(): void
    {
        foreach (['estudiante', 'profesor', 'director', 'padre'] as $rol) {
            DB::table('roles')->updateOrInsert(['nombre' => $rol], ['nombre' => $rol]);
        }
        DB::table('secciones')->updateOrInsert(['nombre' => 'General'], ['nombre' => 'General']);
        DB::table('tipos_ejercicio')->updateOrInsert(['nombre' => 'Repertorio'], ['nombre' => 'Repertorio']);
        DB::table('tipos_elencos')->updateOrInsert(['nombre' => 'Orquesta'], ['nombre' => 'Orquesta']);
    }

    protected function crearUsuario(string $rol, string $contrasena = 'secret123', array $extra = []): int
    {
        $this->secuenciaCorreo++;

        return DB::table('usuarios')->insertGetId(array_merge([
            'correo' => $rol.$this->secuenciaCorreo.'.'.uniqid().'@msea.test',
            'contrasena' => Hash::make($contrasena),
            'nombres' => ucfirst($rol).$this->secuenciaCorreo,
            'apellido_paterno' => 'Prueba',
            'id_rol' => DB::table('roles')->where('nombre', $rol)->value('id_rol'),
        ], $extra), 'id_usuario');
    }

    /** @return array{usuario:int, profesor:int} */
    protected function crearProfesor(): array
    {
        $idUsuario = $this->crearUsuario('profesor');

        return ['usuario' => $idUsuario, 'profesor' => DB::table('profesores')->insertGetId(['id_usuario' => $idUsuario], 'id_profesor')];
    }

    /** @return array{usuario:int, estudiante:int} */
    protected function crearEstudiante(?int $idProfesor = null, ?int $idElenco = null, array $extraUsuario = []): array
    {
        $idUsuario = $this->crearUsuario('estudiante', 'secret123', $extraUsuario);
        $idEstudiante = DB::table('estudiantes')->insertGetId([
            'id_usuario' => $idUsuario,
            'id_profesor' => $idProfesor,
            'id_elenco' => $idElenco,
            'id_seccion' => DB::table('secciones')->value('id_seccion'),
            'fecha_ingreso' => now()->toDateString(),
        ], 'id_estudiante');

        return ['usuario' => $idUsuario, 'estudiante' => $idEstudiante];
    }

    /** @return array{usuario:int, padre:int} */
    protected function crearPadre(array $hijos = []): array
    {
        $idUsuario = $this->crearUsuario('padre');
        $idPadre = DB::table('padres')->insertGetId(['id_usuario' => $idUsuario, 'created_at' => now(), 'updated_at' => now()], 'id_padre');
        foreach ($hijos as $idEstudiante) {
            DB::table('estudiante_padre')->insert(['id_padre' => $idPadre, 'id_estudiante' => $idEstudiante, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['usuario' => $idUsuario, 'padre' => $idPadre];
    }

    protected function crearElenco(string $nombre = 'Elenco Prueba'): int
    {
        return DB::table('elencos')->insertGetId([
            'nombre' => $nombre.' '.uniqid(),
            'id_tipo' => DB::table('tipos_elencos')->value('id_tipo'),
        ], 'id_elenco');
    }

    protected function crearTarea(int $idProfesor, ?int $idEstudiante, ?int $idElenco = null, int $xp = 100): int
    {
        return DB::table('tareas')->insertGetId([
            'titulo' => 'Tarea '.uniqid(),
            'id_profesor' => $idProfesor,
            'id_estudiante' => $idEstudiante,
            'id_elenco' => $idElenco,
            'xp_recompensa' => $xp,
            'estado' => 'pendiente',
            'fecha_creacion' => now(),
        ], 'id_tarea');
    }

    protected function sesionDe(int $idUsuario): static
    {
        $rol = DB::table('usuarios as u')->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.id_usuario', $idUsuario)->value('r.nombre');

        return $this->withSession(['usuario_id' => $idUsuario, 'rol' => $rol]);
    }

    /** Un WAV real y valido (silencio de 0,1 s) para probar la deteccion de MIME. */
    protected function audioWav(string $nombre = 'grabacion.wav'): UploadedFile
    {
        $muestras = 800;
        $datos = str_repeat("\x00\x00", $muestras);
        $wav = 'RIFF'.pack('V', 36 + strlen($datos)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            .'data'.pack('V', strlen($datos)).$datos;

        $ruta = tempnam(sys_get_temp_dir(), 'wav');
        file_put_contents($ruta, $wav);

        return new UploadedFile($ruta, $nombre, 'audio/wav', null, true);
    }

    protected function xpDe(int $idEstudiante): int
    {
        return (int) DB::table('gamificacion_perfiles')->where('id_estudiante', $idEstudiante)->value('xp_total');
    }
}
