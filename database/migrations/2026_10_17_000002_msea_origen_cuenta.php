<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de la cuenta y verificacion del correo (auditoria 2026-10, SEC-02).
 *
 * - origen: 'registro' cuando la cuenta se creo desde el registro publico (sin
 *   comprobar que el correo sea de quien se registra). Las cuentas creadas por la
 *   direccion quedan en null.
 * - correo_verificado_at: fecha en que se comprobo que el correo es de su dueno
 *   (por ahora, al entrar con Google, que entrega el correo verificado).
 *
 * Aditiva: no cambia datos existentes. down() solo quita estas dos columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'origen')) {
                $table->string('origen', 20)->nullable();
            }
            if (! Schema::hasColumn('usuarios', 'correo_verificado_at')) {
                $table->timestamp('correo_verificado_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['origen', 'correo_verificado_at']);
        });
    }
};
