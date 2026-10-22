<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Ejercicios de técnica de mano izquierda (patrones de dedos) para los cuatro instrumentos.
 * - Digitalizador: métodos en PDF que sube el director; se reconocen solo las páginas elegidas,
 *   se revisan y cada ejercicio aprobado pasa a la biblioteca (categoría "metodo").
 * Aditiva; down() quita las tablas nuevas y los ejercicios de técnica sin tareas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digitalizaciones')) {
            Schema::create('digitalizaciones', function (Blueprint $table) {
                $table->increments('id_digitalizacion');
                $table->string('titulo', 150);
                $table->string('metodo', 150)->nullable();      // p. ej. "Ševčík Op. 1, libro 1"
                $table->string('instrumento', 30)->default('violin');
                $table->string('archivo_path', 255);
                $table->string('archivo_nombre', 255);
                $table->unsignedBigInteger('archivo_tamano')->default(0);
                $table->unsignedInteger('paginas_total')->nullable();
                $table->text('paginas');                          // JSON: [4, 6]
                $table->string('estado', 20)->default('pendiente'); // pendiente, procesando, revisar, error
                $table->string('motor', 30)->nullable();          // audiveris o manual
                $table->text('mensaje')->nullable();
                $table->unsignedInteger('creado_por')->nullable();
                $table->timestamps();

                $table->foreign('creado_por')->references('id_usuario')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('digitalizacion_paginas')) {
            Schema::create('digitalizacion_paginas', function (Blueprint $table) {
                $table->increments('id_pagina');
                $table->unsignedInteger('id_digitalizacion');
                $table->unsignedInteger('pagina');
                $table->string('estado', 20)->default('pendiente'); // pendiente, reconocida, manual, error
                $table->string('imagen_path', 255)->nullable();     // vista de la página (PNG)
                $table->string('musicxml_path', 255)->nullable();
                $table->text('ejercicios')->nullable();             // JSON detectado y editado
                $table->text('mensaje')->nullable();
                $table->timestamps();

                $table->foreign('id_digitalizacion')->references('id_digitalizacion')->on('digitalizaciones')->cascadeOnDelete();
                $table->unique(['id_digitalizacion', 'pagina']);
            });
        }

        if (! DB::table('tipos_ejercicio')->where('nombre', 'Tecnica')->exists()) {
            DB::table('tipos_ejercicio')->insert(['nombre' => 'Tecnica']);
        }

        $ahora = now();
        // BD-15: semilla congelada (los 92 ejercicios de esta version). Si
        // TecnicaService cambia, una instalacion nueva siembra lo mismo.
        $semilla = json_decode((string) file_get_contents(database_path('data/2026_10_16_tecnica_mano_izquierda.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($semilla as $fila) {
            $existe = DB::table('biblioteca_ejercicios')->where('categoria', $fila['categoria'])
                ->where('instrumento', $fila['instrumento'])->where('nombre', $fila['nombre'])->exists();
            if (! $existe) {
                DB::table('biblioteca_ejercicios')->insert($fila + ['created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_16_000001_msea_tecnica_y_digitalizador');

        Schema::dropIfExists('digitalizacion_paginas');
        Schema::dropIfExists('digitalizaciones');

        $ids = DB::table('biblioteca_ejercicios')->where('categoria', 'tecnica_mano_izquierda')->pluck('id_biblioteca');
        foreach ($ids as $id) {
            if (! DB::table('tareas')->where('id_biblioteca', $id)->exists()) {
                DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->delete();
            }
        }
    }
};
