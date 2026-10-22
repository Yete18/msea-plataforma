<?php

use App\Support\ReversionSegura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinculo con la cuenta de Google (identificador "sub", estable aunque cambie el correo). Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'google_sub')) {
                $table->string('google_sub', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        ReversionSegura::verificar('2026_10_12_000001_msea_login_google');

        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'google_sub')) {
                $table->dropUnique(['google_sub']);
                $table->dropColumn('google_sub');
            }
        });
    }
};
