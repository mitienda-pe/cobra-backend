<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class StandardizeInvoiceTable extends Migration
{
    public function up()
    {
        try {
            // Verificar si la tabla existe antes de intentar modificarla
            $tableExists = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='invoices'")->getNumRows() > 0;
            
            if (!$tableExists) {
                echo "La tabla 'invoices' no existe\n";
                return;
            }
            
            // Obtener la estructura actual de la tabla
            $query = $this->db->query("PRAGMA table_info(invoices)");
            $columns = $query->getResultArray();
            
            // Mapear nombres de columnas
            $columnNames = array_column($columns, 'name');
            
            // Verificar si existe la columna invoice_number pero no number
            if (in_array('invoice_number', $columnNames) && !in_array('number', $columnNames)) {
                // Crear una consulta que seleccione todas las columnas existentes
                $selectColumns = [];
                foreach ($columnNames as $column) {
                    if ($column === 'invoice_number') {
                        $selectColumns[] = 'invoice_number as number';
                    } else if ($column === 'amount' && !in_array('total_amount', $columnNames)) {
                        $selectColumns[] = 'amount as total_amount';
                    } else {
                        $selectColumns[] = $column;
                    }
                }
                
                $selectSql = implode(', ', $selectColumns);
                
                // Crear tabla temporal con la estructura corregida
                $this->db->query("CREATE TABLE invoices_temp AS SELECT $selectSql FROM invoices");
                
                // Eliminar tabla original
                $this->db->query("DROP TABLE invoices");
                
                // Renombrar tabla temporal a la original
                $this->db->query("ALTER TABLE invoices_temp RENAME TO invoices");
                
                echo "Estructura de la tabla 'invoices' estandarizada\n";
            }
            // Verificar si existe la columna amount pero no total_amount
            else if (in_array('amount', $columnNames) && !in_array('total_amount', $columnNames)) {
                // Crear una consulta que seleccione todas las columnas existentes
                $selectColumns = [];
                foreach ($columnNames as $column) {
                    if ($column === 'amount') {
                        $selectColumns[] = 'amount as total_amount';
                    } else {
                        $selectColumns[] = $column;
                    }
                }
                
                $selectSql = implode(', ', $selectColumns);
                
                // Crear tabla temporal con la estructura corregida
                $this->db->query("CREATE TABLE invoices_temp AS SELECT $selectSql FROM invoices");
                
                // Eliminar tabla original
                $this->db->query("DROP TABLE invoices");
                
                // Renombrar tabla temporal a la original
                $this->db->query("ALTER TABLE invoices_temp RENAME TO invoices");
                
                echo "Columna 'amount' renombrada a 'total_amount' en la tabla 'invoices'\n";
            }
            // Verificar si la estructura ya es la correcta
            else if (in_array('number', $columnNames) && in_array('total_amount', $columnNames)) {
                echo "La tabla 'invoices' ya tiene la estructura correcta\n";
            }
            // Caso en que faltan ambas columnas o hay alguna otra estructura no prevista
            else {
                echo "No se pudo determinar la estructura actual de la tabla 'invoices'\n";
            }
        } catch (\Exception $e) {
            echo "Error al estandarizar la tabla 'invoices': " . $e->getMessage() . "\n";
        }
    }

    public function down()
    {
        // No se implementa la reversión para evitar pérdida de datos
        echo "La migración no se puede revertir automáticamente\n";
    }
}
