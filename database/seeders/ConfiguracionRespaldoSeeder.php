<?php

namespace Database\Seeders;

use App\Models\ConfiguracionRespaldo;
use Illuminate\Database\Seeder;

class ConfiguracionRespaldoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ConfiguracionRespaldo::singleton();
    }
}
