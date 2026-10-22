<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuarios') || Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id_rol');
            $table->string('nombre', 50)->unique();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id_usuario');
            $table->string('correo', 100)->unique();
            $table->text('contrasena');
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->string('ci', 20)->nullable()->unique();
            $table->string('celular', 20)->nullable();
            $table->text('direccion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('foto')->nullable();
            $table->unsignedInteger('id_rol');

            $table->foreign('id_rol')->references('id_rol')->on('roles');
        });

        Schema::create('tipos_elencos', function (Blueprint $table) {
            $table->increments('id_tipo');
            $table->string('nombre', 50)->unique();
        });

        Schema::create('elencos', function (Blueprint $table) {
            $table->increments('id_elenco');
            $table->string('nombre', 100);
            $table->unsignedInteger('id_tipo');

            $table->foreign('id_tipo')->references('id_tipo')->on('tipos_elencos')->restrictOnDelete();
        });

        Schema::create('secciones', function (Blueprint $table) {
            $table->increments('id_seccion');
            $table->string('nombre', 50)->unique();
        });

        Schema::create('profesores', function (Blueprint $table) {
            $table->increments('id_profesor');
            $table->unsignedInteger('id_usuario')->unique();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('instrumentos', function (Blueprint $table) {
            $table->increments('id_instrumento');
            $table->string('nombre', 50)->unique();
        });

        Schema::create('estudiantes', function (Blueprint $table) {
            $table->increments('id_estudiante');
            $table->unsignedInteger('id_usuario')->unique();
            $table->date('fecha_ingreso')->nullable();
            $table->unsignedInteger('id_elenco')->nullable();
            $table->unsignedInteger('id_profesor')->nullable();
            $table->unsignedInteger('id_seccion');
            $table->decimal('monto_pago', 10, 2)->nullable();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
            $table->foreign('id_elenco')->references('id_elenco')->on('elencos')->nullOnDelete();
            $table->foreign('id_profesor')->references('id_profesor')->on('profesores');
            $table->foreign('id_seccion')->references('id_seccion')->on('secciones');
        });

        Schema::create('usuario_instrumento', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_usuario');
            $table->unsignedInteger('id_instrumento');

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
            $table->foreign('id_instrumento')->references('id_instrumento')->on('instrumentos')->cascadeOnDelete();
            $table->unique(['id_usuario', 'id_instrumento']);
        });

        Schema::create('tareas', function (Blueprint $table) {
            $table->increments('id_tarea');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->unsignedInteger('id_profesor');
            $table->unsignedInteger('id_elenco')->nullable();
            $table->unsignedInteger('id_estudiante')->nullable();

            $table->foreign('id_profesor')->references('id_profesor')->on('profesores')->cascadeOnDelete();
            $table->foreign('id_elenco')->references('id_elenco')->on('elencos')->nullOnDelete();
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->nullOnDelete();
        });

        Schema::create('tipos_ejercicio', function (Blueprint $table) {
            $table->increments('id_tipo');
            $table->string('nombre', 50)->unique();
        });

        Schema::create('ejercicios', function (Blueprint $table) {
            $table->increments('id_ejercicio');
            $table->unsignedInteger('id_tipo');
            $table->text('descripcion')->nullable();
            $table->text('archivo')->nullable();
            $table->unsignedInteger('creado_por');

            $table->foreign('id_tipo')->references('id_tipo')->on('tipos_ejercicio');
            $table->foreign('creado_por')->references('id_profesor')->on('profesores')->cascadeOnDelete();
        });

        Schema::create('tarea_ejercicio', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_tarea');
            $table->unsignedInteger('id_ejercicio');

            $table->foreign('id_tarea')->references('id_tarea')->on('tareas')->cascadeOnDelete();
            $table->foreign('id_ejercicio')->references('id_ejercicio')->on('ejercicios')->cascadeOnDelete();
            $table->unique(['id_tarea', 'id_ejercicio']);
        });

        Schema::create('progreso', function (Blueprint $table) {
            $table->increments('id_progreso');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_ejercicio');
            $table->decimal('precision', 5, 2)->nullable();
            $table->decimal('puntaje', 6, 2)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_ejercicio')->references('id_ejercicio')->on('ejercicios')->cascadeOnDelete();
        });

        Schema::create('practicas', function (Blueprint $table) {
            $table->increments('id_practica');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_ejercicio');
            $table->string('tiempo')->nullable();
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_ejercicio')->references('id_ejercicio')->on('ejercicios')->cascadeOnDelete();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->increments('id_feedback');
            $table->unsignedInteger('id_profesor');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_ejercicio');
            $table->text('comentario');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('id_profesor')->references('id_profesor')->on('profesores')->cascadeOnDelete();
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_ejercicio')->references('id_ejercicio')->on('ejercicios')->cascadeOnDelete();
        });

        Schema::create('recompensas', function (Blueprint $table) {
            $table->increments('id_recompensa');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
        });

        Schema::create('estudiante_recompensa', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_recompensa');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_recompensa')->references('id_recompensa')->on('recompensas')->cascadeOnDelete();
            $table->unique(['id_estudiante', 'id_recompensa']);
        });

        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('correo', 100)->index();
            $table->text('codigo');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('progreso', function (Blueprint $table) {
            $table->index('id_estudiante', 'idx_progreso_estudiante');
            $table->index('id_ejercicio', 'idx_progreso_ejercicio');
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->index('id_profesor', 'idx_tareas_profesor');
        });

        Schema::table('estudiantes', function (Blueprint $table) {
            $table->index('id_profesor', 'idx_estudiantes_profesor');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE progreso ADD CONSTRAINT chk_estado_progreso CHECK (estado in ('pendiente', 'completado'))");
            DB::statement('ALTER TABLE tareas ADD CONSTRAINT chk_asignacion_tarea CHECK (((id_elenco IS NOT NULL)::int + (id_estudiante IS NOT NULL)::int) = 1)');
        }

        $this->createRankingView();
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_05_26_000001_create_msea_schema');

        DB::statement('DROP VIEW IF EXISTS ranking_estudiantes');

        foreach ([
            'password_reset_codes',
            'estudiante_recompensa',
            'recompensas',
            'feedback',
            'practicas',
            'progreso',
            'tarea_ejercicio',
            'ejercicios',
            'tipos_ejercicio',
            'tareas',
            'usuario_instrumento',
            'estudiantes',
            'instrumentos',
            'profesores',
            'secciones',
            'elencos',
            'tipos_elencos',
            'usuarios',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createRankingView(): void
    {
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
    }
};
