<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UpdateInvoicesSeeder extends Seeder
{
    public function run()
    {
        // Obtener todas las facturas que no tienen fecha de emisión
        $invoices = $this->db->table('invoices')
                           ->where('issue_date IS NULL')
                           ->get()
                           ->getResultArray();
        
        echo "Actualizando " . count($invoices) . " facturas sin fecha de emisión...\n";
        
        foreach ($invoices as $invoice) {
            // Crear una fecha de emisión basada en la fecha de vencimiento
            // (5-30 días antes de la fecha de vencimiento)
            $dueDate = new \DateTime($invoice['due_date']);
            $daysToSubtract = rand(5, 30);
            $issueDate = (clone $dueDate)->modify("-{$daysToSubtract} days");
            
            // Actualizar la factura
            $this->db->table('invoices')
                   ->where('id', $invoice['id'])
                   ->update([
                       'issue_date' => $issueDate->format('Y-m-d'),
                       'updated_at' => date('Y-m-d H:i:s')
                   ]);
            
            echo "Factura ID {$invoice['id']} actualizada con fecha de emisión: {$issueDate->format('Y-m-d')}\n";
        }
        
        echo "Actualización de facturas completada.\n";
    }
}
