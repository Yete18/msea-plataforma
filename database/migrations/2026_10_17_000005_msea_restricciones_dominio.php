<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * BD-07: reglas de datos dentro de la base (antes solo en los controladores).
 * Solo PostgreSQL. Si ya existen filas que no cumplen una regla, NO se borran
 * ni se modifican: la restriccion se crea como NOT VALID (vale para filas
 * nuevas) y se avisa en el log para revisarlas a mano.
 */
return new class extends Migration
{
    private const CHECKS = [
        'chk_entrega_estado' => ['entregas_tareas', "estado IN ('pendiente', 'entregada', 'devuelta', 'calificada')"],
        'chk_entrega_calificacion' => ['entregas_tareas', 'calificacion IS NULL OR (calificacion >= 0 AND calificacion <= 100)'],
        'chk_tarea_estado' => ['tareas', "estado IN ('pendiente', 'entregada', 'devuelta', 'calificada')"],
        'chk_tarea_xp' => ['tareas', 'xp_recompensa IS NULL OR xp_recompensa >= 0'],
        'chk_practica_estado' => ['practicas_autonomas', "estado IN ('en_curso', 'finalizada', 'caducada')"],
        'chk_practica_numeros' => ['practicas_autonomas', 'duracion_segundos >= 0 AND xp_ganado >= 0'],
        'chk_leccion_progreso_estado' => ['leccion_progreso', "estado IN ('pendiente', 'completada')"],
        'chk_perfil_xp' => ['gamificacion_perfiles', 'xp_total >= 0 AND racha_actual >= 0 AND nivel >= 1'],
        'chk_logro_xp' => ['logros', 'xp_bonus >= 0'],
        'chk_meta_diaria' => ['estudiantes', 'meta_diaria_minutos IS NULL OR (meta_diaria_minutos >= 0 AND meta_diaria_minutos <= 600)'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHECKS as $nombre => [$tabla, $regla]) {
            if (! Schema::hasTable($tabla) || $this->existe($nombre)) {
                continue;
            }
            $invalidas = (int) DB::scalar("SELECT count(*) FROM {$tabla} WHERE NOT ({$regla})");
            $sufijo = $invalidas > 0 ? ' NOT VALID' : '';
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} CHECK ({$regla}){$sufijo}");
            if ($invalidas > 0) {
                Log::warning("MSEA: {$invalidas} fila(s) de {$tabla} no cumplen {$nombre}. Revisalas y luego: ALTER TABLE {$tabla} VALIDATE CONSTRAINT {$nombre};");
            }
        }

        // Nombre de elenco unico (sin distinguir mayusculas), si no hay duplicados.
        $duplicados = (int) DB::scalar('SELECT count(*) FROM (SELECT lower(nombre) FROM elencos GROUP BY 1 HAVING count(*) > 1) d');
        if ($duplicados === 0) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS elencos_nombre_lower_unique ON elencos (lower(nombre))');
        } else {
            Log::warning("MSEA: hay {$duplicados} nombre(s) de elenco repetidos; no se creo el indice unico. Renombralos y vuelve a migrar.");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHECKS as $nombre => [$tabla]) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$nombre}");
        }
        DB::statement('DROP INDEX IF EXISTS elencos_nombre_lower_unique');
    }

    private function existe(string $nombre): bool
    {
        return DB::table('pg_constraint')->where('conname', $nombre)->exists();
    }
};
