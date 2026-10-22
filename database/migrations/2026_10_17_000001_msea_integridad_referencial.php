<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integridad referencial (auditoria 2026-10, hallazgos BD-01 y BD-02).
 *
 * Antes, borrar un profesor o un usuario arrastraba en cascada sus tareas, las
 * entregas y grabaciones de los alumnos y su XP; y borrar un elenco con tareas
 * fallaba en PostgreSQL (SET NULL choca con chk_asignacion_tarea). Ahora estas
 * claves son RESTRICT: el unico borrado previsto es el logico (usuarios.activo).
 *
 * No modifica datos. Solo PostgreSQL (motor de produccion y de la suite
 * phpunit.pgsql.xml); en SQLite las claves foraneas no se pueden alterar sin
 * reconstruir tablas y la suite rapida no depende de ellas.
 * Reversion: down() restaura exactamente las definiciones anteriores.
 */
return new class extends Migration
{
    /** tabla, columna, tabla referida, columna referida, ON DELETE anterior */
    private const CLAVES = [
        ['tareas', 'id_profesor', 'profesores', 'id_profesor', 'CASCADE'],
        ['tareas', 'id_elenco', 'elencos', 'id_elenco', 'SET NULL'],
        ['tareas', 'id_estudiante', 'estudiantes', 'id_estudiante', 'SET NULL'],
        ['ejercicios', 'creado_por', 'profesores', 'id_profesor', 'CASCADE'],
        ['estudiantes', 'id_usuario', 'usuarios', 'id_usuario', 'CASCADE'],
        ['profesores', 'id_usuario', 'usuarios', 'id_usuario', 'CASCADE'],
        ['entregas_tareas', 'id_estudiante', 'estudiantes', 'id_estudiante', 'CASCADE'],
    ];

    public function up(): void
    {
        $this->aplicar(fn ($anterior) => 'RESTRICT');
    }

    public function down(): void
    {
        $this->aplicar(fn ($anterior) => $anterior);
    }

    private function aplicar(callable $accion): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CLAVES as [$tabla, $columna, $refTabla, $refColumna, $anterior]) {
            $nombre = $this->nombreClave($tabla, $columna);
            if ($nombre === null) {
                continue; // la base heredada no tiene esa clave: no se inventa
            }
            $onDelete = $accion($anterior);
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT {$nombre}");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} FOREIGN KEY ({$columna}) REFERENCES {$refTabla}({$refColumna}) ON DELETE {$onDelete}");
        }
    }

    private function nombreClave(string $tabla, string $columna): ?string
    {
        $fila = DB::selectOne(
            "SELECT c.conname FROM pg_constraint c
             JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
             WHERE c.contype = 'f' AND c.conrelid = ?::regclass AND a.attname = ? LIMIT 1",
            [$tabla, $columna]
        );

        return $fila->conname ?? null;
    }
};
