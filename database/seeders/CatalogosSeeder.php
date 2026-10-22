<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogos que la aplicacion necesita para funcionar: roles, niveles
 * formativos, instrumentos, tipos de elenco y de ejercicio, y logros.
 *
 * Es idempotente (se puede ejecutar varias veces) y NO crea cuentas, asi que
 * es apto para produccion:  php artisan db:seed --class=CatalogosSeeder --force
 */
class CatalogosSeeder extends Seeder
{
    public const ROLES = ['estudiante', 'profesor', 'director', 'padre'];

    public const SECCIONES = ['General', 'Preparatoria', 'Inicial', 'Intermedio', 'Prejuvenil', 'Orquesta Juvenil'];

    public const INSTRUMENTOS = ['Violin', 'Viola', 'Violonchelo', 'Contrabajo'];

    public const TIPOS_ELENCO = ['Orquesta', 'Elenco'];

    public const TIPOS_EJERCICIO = ['Ritmo', 'Escala', 'Teoria', 'Afinacion', 'Repertorio', 'Tecnica'];

    /** Codigos fijos que usa GamificationService. */
    public const LOGROS = [
        ['codigo' => 'primer_paso', 'nombre' => 'Primer paso musical', 'descripcion' => 'Completa tu primera actividad.', 'xp_bonus' => 10],
        ['codigo' => '500_xp', 'nombre' => '500 XP', 'descripcion' => 'Acumula 500 puntos de experiencia.', 'xp_bonus' => 25],
        ['codigo' => 'nivel_4', 'nombre' => 'Nivel 4', 'descripcion' => 'Alcanza el nivel 4.', 'xp_bonus' => 50],
        ['codigo' => 'racha_3', 'nombre' => 'Racha de 3 dias', 'descripcion' => 'Practica tres dias seguidos.', 'xp_bonus' => 20],
        ['codigo' => 'racha_7', 'nombre' => 'Semana perfecta', 'descripcion' => 'Practica siete dias seguidos.', 'xp_bonus' => 100],
    ];

    public function run(): void
    {
        $this->nombres('roles', self::ROLES);
        $this->nombres('secciones', self::SECCIONES);
        $this->nombres('instrumentos', self::INSTRUMENTOS);
        $this->nombres('tipos_elencos', self::TIPOS_ELENCO);
        $this->nombres('tipos_ejercicio', self::TIPOS_EJERCICIO);

        if (Schema::hasTable('logros')) {
            foreach (self::LOGROS as $logro) {
                // Solo se crea si falta: no pisa textos o XP que el director haya editado.
                if (! DB::table('logros')->where('codigo', $logro['codigo'])->exists()) {
                    DB::table('logros')->insert($logro + ['activo' => true]);
                }
            }
        }
    }

    private function nombres(string $tabla, array $nombres): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        foreach ($nombres as $nombre) {
            // Comparacion sin mayusculas para no duplicar "violin" y "Violin".
            $existe = DB::table($tabla)->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->exists();
            if (! $existe) {
                DB::table($tabla)->insert(['nombre' => $nombre]);
            }
        }
    }
}
