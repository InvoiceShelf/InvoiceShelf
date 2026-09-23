<?php

namespace Modules\DemoProbe\Demo;

use Illuminate\Database\Seeder;

/**
 * Stands in for a module's demo seeder in DemoResetTest.
 */
class DemoSeeder extends Seeder
{
    public static ?int $companyId = null;

    public function run(int $companyId): void
    {
        self::$companyId = $companyId;
    }
}
