<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria 2026-10: endurecimiento de seguridad y fuente unica de XP.
 *
 * Solo agrega columnas/tablas e indices. No elimina tablas antiguas
 * (practicas, feedback, recompensas): quedan documentadas como legado.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Usuarios: desactivacion logica y correos normalizados ---------
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'activo')) {
                $table->boolean('activo')->default(true);
            }
            if (! Schema::hasColumn('usuarios', 'desactivado_at')) {
                $table->timestamp('desactivado_at')->nullable();
            }
        });

        $this->normalizarCorreos();

        // --- Tokens moviles con expiracion ---------------------------------
        Schema::table('mobile_api_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_api_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('mobile_api_tokens', 'ip')) {
                $table->string('ip', 45)->nullable();
            }
        });
        DB::table('mobile_api_tokens')->whereNull('expires_at')->update([
            'expires_at' => now()->addDays((int) config('msea.api.token_dias', 30)),
        ]);

        // --- Codigos de recuperacion: contador de intentos ------------------
        Schema::table('password_reset_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('password_reset_codes', 'intentos')) {
                $table->unsignedInteger('intentos')->default(0);
            }
        });

        // --- Bitacora de auditoria -----------------------------------------
        if (! Schema::hasTable('auditoria')) {
            Schema::create('auditoria', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_usuario')->nullable();
                $table->string('accion', 60);
                $table->text('detalle')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->nullOnDelete();
                $table->index(['accion', 'created_at'], 'idx_auditoria_accion_fecha');
                $table->index(['id_usuario', 'created_at'], 'idx_auditoria_usuario_fecha');
            });
        }

        // --- Libro de XP: fuente unica e idempotente ------------------------
        if (! Schema::hasTable('xp_movimientos')) {
            Schema::create('xp_movimientos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_estudiante');
                $table->integer('xp');
                $table->string('fuente', 40);
                $table->string('clave', 150)->unique();
                $table->string('descripcion', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
                $table->index(['id_estudiante', 'created_at'], 'idx_xp_estudiante_fecha');
            });
        }

        // Saldo inicial: el XP que ya existia queda registrado en el libro.
        foreach (DB::table('gamificacion_perfiles')->where('xp_total', '>', 0)->get() as $perfil) {
            DB::table('xp_movimientos')->insertOrIgnore([
                'id_estudiante' => $perfil->id_estudiante,
                'xp' => (int) $perfil->xp_total,
                'fuente' => 'saldo_inicial',
                'clave' => 'saldo_inicial:'.$perfil->id_estudiante,
                'descripcion' => 'XP acumulado antes de la auditoria',
                'created_at' => now(),
            ]);
        }

        // --- Practicas medidas por el servidor -------------------------------
        Schema::table('practicas_autonomas', function (Blueprint $table) {
            if (! Schema::hasColumn('practicas_autonomas', 'estado')) {
                $table->string('estado', 20)->default('finalizada');
            }
            if (! Schema::hasColumn('practicas_autonomas', 'id_ejercicio')) {
                $table->unsignedInteger('id_ejercicio')->nullable();
                $table->foreign('id_ejercicio')->references('id_ejercicio')->on('ejercicios')->nullOnDelete();
            }
            if (! Schema::hasColumn('practicas_autonomas', 'iniciada_at')) {
                $table->timestamp('iniciada_at')->nullable();
            }
            if (! Schema::hasColumn('practicas_autonomas', 'finalizada_at')) {
                $table->timestamp('finalizada_at')->nullable();
            }
            if (! Schema::hasColumn('practicas_autonomas', 'metricas')) {
                $table->text('metricas')->nullable();
            }
            if (! Schema::hasColumn('practicas_autonomas', 'metricas_verificadas')) {
                $table->boolean('metricas_verificadas')->default(false);
            }
        });

        // --- Teoria: intentos evaluados en servidor --------------------------
        if (! Schema::hasTable('teoria_intentos')) {
            Schema::create('teoria_intentos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_teoria');
                $table->unsignedInteger('id_estudiante');
                $table->string('respuesta', 150);
                $table->boolean('correcta')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_teoria')->references('id_teoria')->on('teoria_contenidos')->cascadeOnDelete();
                $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
                $table->index(['id_estudiante', 'id_teoria'], 'idx_teoria_intentos_est');
            });
        }

        // --- Entregas con archivo privado y estado de devolucion -------------
        Schema::table('entregas_tareas', function (Blueprint $table) {
            foreach ([
                'archivo_path' => fn () => $table->string('archivo_path', 255)->nullable(),
                'archivo_nombre' => fn () => $table->string('archivo_nombre', 255)->nullable(),
                'archivo_mime' => fn () => $table->string('archivo_mime', 100)->nullable(),
                'archivo_tamano' => fn () => $table->unsignedBigInteger('archivo_tamano')->nullable(),
                'devuelta_at' => fn () => $table->timestamp('devuelta_at')->nullable(),
            ] as $columna => $crear) {
                if (! Schema::hasColumn('entregas_tareas', $columna)) {
                    $crear();
                }
            }
        });

        // --- Tareas: material adjunto del profesor ---------------------------
        Schema::table('tareas', function (Blueprint $table) {
            foreach ([
                'material_path' => fn () => $table->string('material_path', 255)->nullable(),
                'material_nombre' => fn () => $table->string('material_nombre', 255)->nullable(),
                'material_mime' => fn () => $table->string('material_mime', 100)->nullable(),
                'material_tamano' => fn () => $table->unsignedBigInteger('material_tamano')->nullable(),
            ] as $columna => $crear) {
                if (! Schema::hasColumn('tareas', $columna)) {
                    $crear();
                }
            }
        });

        // --- Contenido publicable y logros activables -----------------------
        foreach (['modulos', 'lecciones'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (! Schema::hasColumn($tabla, 'publicado')) {
                    $table->boolean('publicado')->default(true);
                }
            });
        }
        Schema::table('logros', function (Blueprint $table) {
            if (! Schema::hasColumn('logros', 'activo')) {
                $table->boolean('activo')->default(true);
            }
        });
        Schema::table('teoria_contenidos', function (Blueprint $table) {
            if (! Schema::hasColumn('teoria_contenidos', 'activo')) {
                $table->boolean('activo')->default(true);
            }
        });

        // --- Indices para consultas frecuentes -------------------------------
        Schema::table('notificaciones', fn (Blueprint $t) => $t->index(['id_usuario', 'created_at'], 'idx_notif_usuario_fecha'));
        Schema::table('practicas_autonomas', fn (Blueprint $t) => $t->index(['id_estudiante', 'created_at'], 'idx_practicas_est_fecha'));
        Schema::table('entregas_tareas', fn (Blueprint $t) => $t->index('id_estudiante', 'idx_entregas_estudiante'));
        Schema::table('tareas', function (Blueprint $t) {
            $t->index('id_estudiante', 'idx_tareas_estudiante');
            $t->index('id_elenco', 'idx_tareas_elenco');
        });
        Schema::table('mensajes', fn (Blueprint $t) => $t->index(['id_destinatario', 'created_at'], 'idx_mensajes_dest_fecha'));

        // --- Ranking: misma fuente que el perfil (gamificacion_perfiles) -----
        DB::statement('DROP VIEW IF EXISTS ranking_estudiantes');
        DB::statement(<<<'SQL'
CREATE VIEW ranking_estudiantes AS
SELECT
    e.id_estudiante,
    u.nombres,
    u.apellido_paterno,
    COALESCE(g.xp_total, 0) AS puntaje_total,
    RANK() OVER (ORDER BY COALESCE(g.xp_total, 0) DESC) AS posicion
FROM estudiantes e
JOIN usuarios u ON e.id_usuario = u.id_usuario
LEFT JOIN gamificacion_perfiles g ON g.id_estudiante = e.id_estudiante
WHERE u.activo = TRUE
SQL);
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_10_000001_msea_seguridad_y_gamificacion');

        DB::statement('DROP VIEW IF EXISTS ranking_estudiantes');
        DB::statement(<<<'SQL'
CREATE VIEW ranking_estudiantes AS
SELECT
    e.id_estudiante,
    u.nombres,
    u.apellido_paterno,
    COALESCE(SUM(p.puntaje), 0) AS puntaje_total,
    RANK() OVER (ORDER BY COALESCE(SUM(p.puntaje), 0) DESC) AS posicion
FROM estudiantes e
JOIN usuarios u ON e.id_usuario = u.id_usuario
LEFT JOIN progreso p ON e.id_estudiante = p.id_estudiante
GROUP BY e.id_estudiante, u.nombres, u.apellido_paterno
SQL);

        Schema::table('mensajes', fn (Blueprint $t) => $t->dropIndex('idx_mensajes_dest_fecha'));
        Schema::table('tareas', function (Blueprint $t) {
            $t->dropIndex('idx_tareas_estudiante');
            $t->dropIndex('idx_tareas_elenco');
        });
        Schema::table('entregas_tareas', fn (Blueprint $t) => $t->dropIndex('idx_entregas_estudiante'));
        Schema::table('practicas_autonomas', fn (Blueprint $t) => $t->dropIndex('idx_practicas_est_fecha'));
        Schema::table('notificaciones', fn (Blueprint $t) => $t->dropIndex('idx_notif_usuario_fecha'));

        Schema::table('teoria_contenidos', fn (Blueprint $t) => $t->dropColumn('activo'));
        Schema::table('logros', fn (Blueprint $t) => $t->dropColumn('activo'));
        Schema::table('lecciones', fn (Blueprint $t) => $t->dropColumn('publicado'));
        Schema::table('modulos', fn (Blueprint $t) => $t->dropColumn('publicado'));
        Schema::table('tareas', fn (Blueprint $t) => $t->dropColumn(['material_path', 'material_nombre', 'material_mime', 'material_tamano']));
        Schema::table('entregas_tareas', fn (Blueprint $t) => $t->dropColumn(['archivo_path', 'archivo_nombre', 'archivo_mime', 'archivo_tamano', 'devuelta_at']));

        Schema::dropIfExists('teoria_intentos');

        Schema::table('practicas_autonomas', function (Blueprint $t) {
            $t->dropForeign(['id_ejercicio']);
        });
        Schema::table('practicas_autonomas', fn (Blueprint $t) => $t->dropColumn(['estado', 'id_ejercicio', 'iniciada_at', 'finalizada_at', 'metricas', 'metricas_verificadas']));

        Schema::dropIfExists('xp_movimientos');
        Schema::dropIfExists('auditoria');

        Schema::table('password_reset_codes', fn (Blueprint $t) => $t->dropColumn('intentos'));
        Schema::table('mobile_api_tokens', fn (Blueprint $t) => $t->dropColumn(['expires_at', 'ip']));

        DB::statement('DROP INDEX IF EXISTS usuarios_correo_lower_unique');
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn(['activo', 'desactivado_at']));
        // Los correos pasados a minusculas no se revierten (no hay forma segura de saber el original).
    }

    /**
     * Pasa los correos a minusculas solo si no genera choques; los choques se
     * reportan en el log y NO se tocan (ninguna cuenta se elimina).
     */
    private function normalizarCorreos(): void
    {
        $conflictos = DB::table('usuarios')
            ->selectRaw('lower(correo) as correo_normalizado, count(*) as total')
            ->groupByRaw('lower(correo)')
            ->havingRaw('count(*) > 1')
            ->pluck('total', 'correo_normalizado');

        foreach ($conflictos as $correo => $total) {
            Log::warning('MSEA: correos duplicados que solo difieren en mayusculas; revisar manualmente.', [
                'correo_normalizado_hash' => hash('sha256', (string) $correo),
                'cuentas' => $total,
            ]);
        }

        $query = DB::table('usuarios')->whereRaw('correo <> lower(correo)');
        if ($conflictos->isNotEmpty()) {
            $query->whereNotIn(DB::raw('lower(correo)'), $conflictos->keys()->all());
        }
        $query->update(['correo' => DB::raw('lower(correo)')]);

        DB::table('password_reset_codes')->update(['correo' => DB::raw('lower(correo)')]);

        if ($conflictos->isEmpty()) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS usuarios_correo_lower_unique ON usuarios (lower(correo))');
        }
    }
};
