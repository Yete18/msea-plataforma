<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ARQ-04: algunas cuentas antiguas tenian la contrasena guardada en texto
 * plano. Se convierten a hash con el MISMO valor, asi nadie pierde el acceso
 * y la base deja de contener contrasenas legibles. Es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('usuarios')->select(['id_usuario', 'contrasena'])->orderBy('id_usuario')
            ->chunkById(200, function ($usuarios) {
                foreach ($usuarios as $u) {
                    $valor = (string) $u->contrasena;
                    if ($valor === '' || $this->esHash($valor)) {
                        continue;
                    }
                    DB::table('usuarios')->where('id_usuario', $u->id_usuario)
                        ->update(['contrasena' => Hash::make($valor)]);
                }
            }, 'id_usuario');
    }

    /** Copia congelada de AuthService::pareceHash (la migracion no depende del codigo de la app). */
    private function esHash(string $valor): bool
    {
        return preg_match('/^\$(2[abxy]|argon2i|argon2id)\$/', $valor) === 1
            || (password_get_info($valor)['algo'] ?? null) !== null;
    }

    public function down(): void
    {
        // Irreversible a proposito: no se vuelven a guardar contrasenas en claro.
    }
};
