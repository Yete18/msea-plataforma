<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta diaria de practica elegida por el estudiante (minutos). Aditiva.
 * NULL = aun no la eligio (el panel le muestra la bienvenida para elegirla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            if (! Schema::hasColumn('estudiantes', 'meta_diaria_minutos')) {
                $table->unsignedSmallInteger('meta_diaria_minutos')->nullable();
            }
        });
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_11_000001_msea_habitos_estudiante');

        Schema::table('estudiantes', function (Blueprint $table) {
            if (Schema::hasColumn('estudiantes', 'meta_diaria_minutos')) {
                $table->dropColumn('meta_diaria_minutos');
            }
        });
    }
};
