<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CleanAndResetSeeder extends Seeder
{
    public function run()
    {
        // Limpiar las tablas en orden inverso de dependencia
        $this->cleanTable('payments');
        $this->cleanTable('instalments');
        $this->cleanTable('invoices');
        $this->cleanTable('clients');
        $this->cleanTable('portfolio_collectors');
        $this->cleanTable('portfolios');

        // Ejecutar seeders para recrear datos
        $this->call('ClientSeeder');
        $this->call('PortfolioSeeder');
        $this->call('InvoiceSeeder');

        echo "Todas las tablas han sido limpiadas y repobladas con datos de prueba.\n";
    }

    private function cleanTable($tableName)
    {
        try {
            // Verificar si la tabla existe
            $tableExists = $this->db->tableExists($tableName);
            
            if ($tableExists) {
                // Para SQLite, usamos DELETE FROM
                $this->db->query("DELETE FROM {$tableName}");
                // Reiniciar el contador de autoincremento (específico para SQLite)
                $this->db->query("DELETE FROM sqlite_sequence WHERE name = '{$tableName}'");
                echo "Tabla {$tableName} limpiada correctamente.\n";
            } else {
                echo "La tabla {$tableName} no existe.\n";
            }
        } catch (\Exception $e) {
            echo "Error al limpiar la tabla {$tableName}: " . $e->getMessage() . "\n";
        }
    }
}
