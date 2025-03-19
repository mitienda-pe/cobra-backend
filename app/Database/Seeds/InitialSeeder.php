<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        // Usar el seeder simplificado que maneja correctamente las relaciones
        $this->call('InitialSetupSeeder');
    }
}