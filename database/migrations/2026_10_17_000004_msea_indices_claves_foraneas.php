<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD-06: PostgreSQL no crea indices para las claves foraneas. Estos son los
 * que usan los paneles, la mensajeria y los cursos. Solo aditiva: en
 * PostgreSQL se crean con CONCURRENTLY para no bloquear las tablas.
 */
return new class extends Migration
{
    /** CREATE INDEX CONCURRENTLY no puede ir dentro de una transaccion. */
    public $withinTransaction = false;

    private const INDICES = [
        ['estudiantes', 'id_elenco'],
        ['estudiantes', 'id_seccion'],
        ['usuarios', 'id_rol'],
        ['usuario_instrumento', 'id_instrumento'],
        ['elencos', 'id_tipo'],
        ['tareas', 'id_biblioteca'],
        ['tarea_ejercicio', 'id_ejercicio'],
        ['ejercicios', 'id_tipo'],
        ['ejercicios', 'creado_por'],
        ['curso_estudiante', 'id_estudiante'],
        ['cursos', 'id_profesor'],
        ['modulos', 'id_curso'],
        ['lecciones', 'id_modulo'],
        ['leccion_progreso', 'id_estudiante'],
        ['teoria_contenidos', 'id_leccion'],
        ['teoria_intentos', 'id_teoria'],
        ['estudiante_logro', 'id_logro'],
        ['estudiante_padre', 'id_padre'],
        ['mensajes', 'id_remitente'],
        ['mobile_api_tokens', 'id_usuario'],
        ['practicas_autonomas', 'id_ejercicio'],
        ['sesiones_afinacion', 'id_estudiante'],
        ['sesiones_ritmo', 'id_estudiante'],
        ['biblioteca_ejercicios', 'creado_por'],
        ['digitalizaciones', 'creado_por'],
    ];

    public function up(): void
    {
        $concurrente = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';

        foreach (self::INDICES as [$tabla, $columna]) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
                continue;
            }
            DB::statement("CREATE INDEX {$concurrente}IF NOT EXISTS {$this->nombre($tabla, $columna)} ON {$tabla} ({$columna})");
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as [$tabla, $columna]) {
            DB::statement("DROP INDEX IF EXISTS {$this->nombre($tabla, $columna)}");
        }
    }

    private function nombre(string $tabla, string $columna): string
    {
        return "idx_{$tabla}_{$columna}";
    }
};
