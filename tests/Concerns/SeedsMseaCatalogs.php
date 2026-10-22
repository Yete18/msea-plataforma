<?php

namespace Tests\Concerns;

use Database\Seeders\CatalogosSeeder;

trait SeedsMseaCatalogs
{
    /** Los mismos catalogos que se cargan en produccion (CatalogosSeeder). */
    protected function seedMseaCatalogs(): void
    {
        $this->seed(CatalogosSeeder::class);
    }
}
