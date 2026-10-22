<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Los catalogos siempre; las cuentas de demostracion nunca en produccion
        // (MseaDemoSeeder se niega por su cuenta).
        $this->call(CatalogosSeeder::class);

        if (! app()->environment('production')) {
            $this->call(MseaDemoSeeder::class);
        }
    }
}
