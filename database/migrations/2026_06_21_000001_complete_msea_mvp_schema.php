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
        $this->ensureRoles();
        $this->extendExistingTables();
        $this->createAcademicTables();
        $this->createTrackingTables();
        $this->createGamificationTables();
        $this->createCommunicationTables();
        $this->createPracticeTables();
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_06_21_000001_complete_msea_mvp_schema');

        foreach ([
            'sesiones_ritmo',
            'sesiones_afinacion',
            'practicas_autonomas',
            'mensajes',
            'notificaciones',
            'estudiante_logro',
            'logros',
            'gamificacion_perfiles',
            'entregas_tareas',
            'leccion_progreso',
            'curso_estudiante',
            'teoria_contenidos',
            'lecciones',
            'modulos',
            'cursos',
            'estudiante_padre',
            'padres',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function ensureRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach (['director', 'profesor', 'estudiante', 'padre'] as $rol) {
            DB::table('roles')->updateOrInsert(['nombre' => $rol], ['nombre' => $rol]);
        }
    }

    private function extendExistingTables(): void
    {
        if (Schema::hasTable('usuarios')) {
            Schema::table('usuarios', function (Blueprint $table) {
                if (! Schema::hasColumn('usuarios', 'trayectoria')) {
                    $table->text('trayectoria')->nullable();
                }
                if (! Schema::hasColumn('usuarios', 'ultimo_ingreso_at')) {
                    $table->timestamp('ultimo_ingreso_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('tareas')) {
            Schema::table('tareas', function (Blueprint $table) {
                if (! Schema::hasColumn('tareas', 'fecha_limite')) {
                    $table->date('fecha_limite')->nullable();
                }
                if (! Schema::hasColumn('tareas', 'xp_recompensa')) {
                    $table->unsignedInteger('xp_recompensa')->default(30);
                }
                if (! Schema::hasColumn('tareas', 'estado')) {
                    $table->string('estado', 20)->default('pendiente');
                }
            });
        }

        if (Schema::hasTable('ejercicios')) {
            Schema::table('ejercicios', function (Blueprint $table) {
                if (! Schema::hasColumn('ejercicios', 'titulo')) {
                    $table->string('titulo', 150)->nullable();
                }
                if (! Schema::hasColumn('ejercicios', 'dificultad')) {
                    $table->string('dificultad', 30)->default('basico');
                }
                if (! Schema::hasColumn('ejercicios', 'xp')) {
                    $table->unsignedInteger('xp')->default(20);
                }
            });
        }
    }

    private function createAcademicTables(): void
    {
        Schema::create('padres', function (Blueprint $table) {
            $table->increments('id_padre');
            $table->unsignedInteger('id_usuario')->unique();
            $table->string('parentesco', 50)->default('Padre/Madre');
            $table->timestamps();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('estudiante_padre', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_padre');
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_padre')->references('id_padre')->on('padres')->cascadeOnDelete();
            $table->unique(['id_estudiante', 'id_padre']);
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->increments('id_curso');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('instrumento', 50)->nullable();
            $table->string('nivel', 50)->default('Inicial');
            $table->unsignedInteger('id_profesor')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_profesor')->references('id_profesor')->on('profesores')->nullOnDelete();
        });

        Schema::create('modulos', function (Blueprint $table) {
            $table->increments('id_modulo');
            $table->unsignedInteger('id_curso');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(1);
            $table->timestamps();

            $table->foreign('id_curso')->references('id_curso')->on('cursos')->cascadeOnDelete();
        });

        Schema::create('lecciones', function (Blueprint $table) {
            $table->increments('id_leccion');
            $table->unsignedInteger('id_modulo');
            $table->string('titulo', 150);
            $table->text('contenido')->nullable();
            $table->string('tipo', 40)->default('teoria');
            $table->unsignedInteger('orden')->default(1);
            $table->unsignedInteger('xp')->default(20);
            $table->timestamps();

            $table->foreign('id_modulo')->references('id_modulo')->on('modulos')->cascadeOnDelete();
        });

        Schema::create('teoria_contenidos', function (Blueprint $table) {
            $table->increments('id_teoria');
            $table->unsignedInteger('id_leccion')->nullable();
            $table->string('titulo', 150);
            $table->text('pregunta');
            $table->json('opciones')->nullable();
            $table->string('respuesta_correcta', 150)->nullable();
            $table->string('nivel', 50)->default('Inicial');
            $table->unsignedInteger('xp')->default(10);
            $table->timestamps();

            $table->foreign('id_leccion')->references('id_leccion')->on('lecciones')->nullOnDelete();
        });

        Schema::create('curso_estudiante', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_curso');
            $table->unsignedInteger('id_estudiante');
            $table->timestamp('inscrito_at')->useCurrent();

            $table->foreign('id_curso')->references('id_curso')->on('cursos')->cascadeOnDelete();
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->unique(['id_curso', 'id_estudiante']);
        });

        Schema::create('leccion_progreso', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_leccion');
            $table->unsignedInteger('id_estudiante');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();

            $table->foreign('id_leccion')->references('id_leccion')->on('lecciones')->cascadeOnDelete();
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->unique(['id_leccion', 'id_estudiante']);
        });
    }

    private function createTrackingTables(): void
    {
        Schema::create('entregas_tareas', function (Blueprint $table) {
            $table->increments('id_entrega');
            $table->unsignedInteger('id_tarea');
            $table->unsignedInteger('id_estudiante');
            $table->text('comentario_estudiante')->nullable();
            $table->text('archivo')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->decimal('calificacion', 5, 2)->nullable();
            $table->text('comentario_profesor')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('calificado_at')->nullable();
            $table->timestamps();

            $table->foreign('id_tarea')->references('id_tarea')->on('tareas')->cascadeOnDelete();
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->unique(['id_tarea', 'id_estudiante']);
        });
    }

    private function createGamificationTables(): void
    {
        Schema::create('gamificacion_perfiles', function (Blueprint $table) {
            $table->increments('id_gamificacion');
            $table->unsignedInteger('id_estudiante')->unique();
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('nivel')->default(1);
            $table->unsignedInteger('racha_actual')->default(0);
            $table->unsignedInteger('mejor_racha')->default(0);
            $table->date('ultima_practica')->nullable();
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
        });

        Schema::create('logros', function (Blueprint $table) {
            $table->increments('id_logro');
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('xp_bonus')->default(0);
            $table->timestamps();
        });

        Schema::create('estudiante_logro', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('id_logro');
            $table->timestamp('desbloqueado_at')->useCurrent();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
            $table->foreign('id_logro')->references('id_logro')->on('logros')->cascadeOnDelete();
            $table->unique(['id_estudiante', 'id_logro']);
        });
    }

    private function createCommunicationTables(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->increments('id_notificacion');
            $table->unsignedInteger('id_usuario');
            $table->string('titulo', 150);
            $table->text('mensaje');
            $table->string('tipo', 40)->default('info');
            $table->timestamp('leida_at')->nullable();
            $table->timestamps();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->increments('id_mensaje');
            $table->unsignedInteger('id_remitente');
            $table->unsignedInteger('id_destinatario');
            $table->string('asunto', 150)->nullable();
            $table->text('contenido');
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->foreign('id_remitente')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
            $table->foreign('id_destinatario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });
    }

    private function createPracticeTables(): void
    {
        Schema::create('practicas_autonomas', function (Blueprint $table) {
            $table->increments('id_practica_autonoma');
            $table->unsignedInteger('id_estudiante');
            $table->string('tipo', 40)->default('libre');
            $table->unsignedInteger('duracion_segundos')->default(0);
            $table->unsignedInteger('xp_ganado')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
        });

        Schema::create('sesiones_afinacion', function (Blueprint $table) {
            $table->increments('id_sesion_afinacion');
            $table->unsignedInteger('id_estudiante');
            $table->string('instrumento', 50)->nullable();
            $table->string('nota_objetivo', 10)->nullable();
            $table->string('nota_detectada', 10)->nullable();
            $table->decimal('frecuencia', 8, 2)->nullable();
            $table->decimal('desviacion_cents', 7, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
        });

        Schema::create('sesiones_ritmo', function (Blueprint $table) {
            $table->increments('id_sesion_ritmo');
            $table->unsignedInteger('id_estudiante');
            $table->unsignedInteger('bpm')->default(80);
            $table->string('patron', 100)->default('4/4');
            $table->decimal('precision', 5, 2)->nullable();
            $table->unsignedInteger('xp_ganado')->default(0);
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->cascadeOnDelete();
        });
    }
};
