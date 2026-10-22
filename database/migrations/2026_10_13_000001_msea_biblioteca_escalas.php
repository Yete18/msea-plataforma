<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biblioteca de ejercicios técnicos (escalas, arpegios, técnica de arco y de mano
 * izquierda). La administra el director; los profesores la consultan y asignan.
 * Los estudiantes solo ven lo que se les asigna en una tarea.
 *
 * Aditiva: crea una tabla y agrega columnas opcionales. down() las quita.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('biblioteca_ejercicios')) {
            Schema::create('biblioteca_ejercicios', function (Blueprint $table) {
                $table->increments('id_biblioteca');
                $table->string('categoria', 30);          // escala, arpegio, tecnica_arco, tecnica_mano_izquierda
                $table->string('instrumento', 30)->default('violin');
                $table->string('nombre', 120);
                $table->string('dificultad', 15)->default('facil'); // facil, intermedio, dificil
                $table->text('descripcion')->nullable();
                $table->text('datos');                    // JSON con la definición musical
                $table->boolean('activo')->default(true);
                $table->unsignedInteger('orden')->default(0);
                $table->unsignedInteger('creado_por')->nullable();
                $table->timestamps();

                $table->foreign('creado_por')->references('id_usuario')->on('usuarios')->nullOnDelete();
                $table->unique(['categoria', 'instrumento', 'nombre'], 'uq_biblioteca_nombre');
                $table->index(['categoria', 'instrumento', 'activo'], 'idx_biblioteca_filtro');
            });
        }

        Schema::table('tareas', function (Blueprint $table) {
            if (! Schema::hasColumn('tareas', 'id_biblioteca')) {
                $table->unsignedInteger('id_biblioteca')->nullable();
                $table->foreign('id_biblioteca', 'fk_tareas_biblioteca')->references('id_biblioteca')->on('biblioteca_ejercicios')->nullOnDelete();
            }
            if (! Schema::hasColumn('tareas', 'config_escala')) {
                $table->text('config_escala')->nullable(); // JSON: octavas, dirección, ritmo, bpm, compás, tolerancia
            }
        });

        Schema::table('entregas_tareas', function (Blueprint $table) {
            if (! Schema::hasColumn('entregas_tareas', 'resultado_escala')) {
                $table->text('resultado_escala')->nullable(); // JSON medido en el navegador (no verificado)
            }
        });

        if (! DB::table('tipos_ejercicio')->where('nombre', 'Escala')->exists()) {
            DB::table('tipos_ejercicio')->insert(['nombre' => 'Escala']);
        }

        // Escala de referencia para validar el prototipo: La mayor en violín.
        if (! DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', 'violin')->where('nombre', 'La mayor')->exists()) {
            DB::table('biblioteca_ejercicios')->insert([
                'categoria' => 'escala',
                'instrumento' => 'violin',
                'nombre' => 'La mayor',
                'dificultad' => 'facil',
                'descripcion' => 'Escala mayor de La. En una octava empieza en la cuerda La al aire; en dos y tres octavas, en la cuerda Sol con el primer dedo.',
                'datos' => json_encode([
                    'tonica' => 'A',
                    'alteracion' => 0,
                    'modo' => 'mayor',
                    // Octava científica de la tónica según cuántas octavas se toquen (La4 = 440 Hz).
                    'inicio' => ['1' => 4, '2' => 3, '3' => 3],
                    'octavas_max' => 3,
                    // Primera posición, notas ascendentes. Para 3 octavas hay cambios de posición: no se indica.
                    'digitacion' => [
                        '1' => [['La', 0], ['La', 1], ['La', 2], ['La', 3], ['Mi', 0], ['Mi', 1], ['Mi', 2], ['Mi', 3]],
                        '2' => [['Sol', 1], ['Sol', 2], ['Sol', 3], ['Re', 0], ['Re', 1], ['Re', 2], ['Re', 3], ['La', 0],
                            ['La', 1], ['La', 2], ['La', 3], ['Mi', 0], ['Mi', 1], ['Mi', 2], ['Mi', 3]],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'activo' => true,
                'orden' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_13_000001_msea_biblioteca_escalas');

        Schema::table('entregas_tareas', function (Blueprint $table) {
            if (Schema::hasColumn('entregas_tareas', 'resultado_escala')) {
                $table->dropColumn('resultado_escala');
            }
        });

        Schema::table('tareas', function (Blueprint $table) {
            if (Schema::hasColumn('tareas', 'id_biblioteca')) {
                $table->dropForeign('fk_tareas_biblioteca');
                $table->dropColumn('id_biblioteca');
            }
            if (Schema::hasColumn('tareas', 'config_escala')) {
                $table->dropColumn('config_escala');
            }
        });

        Schema::dropIfExists('biblioteca_ejercicios');
    }
};
