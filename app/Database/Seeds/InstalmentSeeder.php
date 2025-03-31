<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InstalmentSeeder extends Seeder
{
    public function run()
    {
        helper('uuid');
        
        echo "Starting Instalment Seeder...\n";
        
        // Obtener todas las facturas
        $invoices = $this->db->table('invoices')
            ->where('deleted_at IS NULL')
            ->get()
            ->getResult();
            
        if (empty($invoices)) {
            throw new \Exception('No invoices found. Please run InvoiceSeeder first.');
        }
        
        echo "Found " . count($invoices) . " invoices\n";
        
        // Fecha actual para referencia
        $currentDate = new \DateTime();
        
        $instalmentsCreated = 0;
        $invoicesProcessed = 0;
        $invoicesSkipped = 0;
        
        // Posibles cantidades de cuotas
        $possibleInstalmentCounts = [1, 2, 3, 6];
        
        // Posibles intervalos entre cuotas (en días)
        $possibleIntervals = [1, 7, 15, 30];
        
        // Para cada factura, verificar si ya tiene cuotas y crear si no tiene
        foreach ($invoices as $invoice) {
            // Verificar si la factura ya tiene cuotas
            $existingInstalments = $this->db->table('instalments')
                ->where('invoice_id', $invoice->id)
                ->where('deleted_at IS NULL')
                ->countAllResults();
                
            if ($existingInstalments > 0) {
                // Usar invoice_number si existe, de lo contrario usar number, o un valor predeterminado
                $invoiceNumber = $invoice->invoice_number ?? $invoice->number ?? "ID: {$invoice->id}";
                echo "Invoice {$invoiceNumber} (ID: {$invoice->id}) already has {$existingInstalments} instalments. Skipping...\n";
                $invoicesSkipped++;
                continue;
            }
            
            // Determinar aleatoriamente el número de cuotas para esta factura
            $numInstalments = $possibleInstalmentCounts[array_rand($possibleInstalmentCounts)];
            
            // Determinar aleatoriamente el intervalo entre cuotas
            $interval = $possibleIntervals[array_rand($possibleIntervals)];
            
            // Usar invoice_number si existe, de lo contrario usar number, o un valor predeterminado
            $invoiceNumber = $invoice->invoice_number ?? $invoice->number ?? "ID: {$invoice->id}";
            echo "Creating {$numInstalments} instalments with {$interval}-day interval for invoice {$invoiceNumber} (ID: {$invoice->id})\n";
            
            // Fecha de vencimiento de la factura como punto de partida
            $invoiceDueDate = new \DateTime($invoice->due_date);
            
            // Monto total de la factura
            $totalAmount = (float)$invoice->total_amount;
            
            // Monto por cuota (redondeado a 2 decimales)
            $amountPerInstalment = round($totalAmount / $numInstalments, 2);
            
            // Ajustar la última cuota para que sume exactamente el total
            $lastInstalmentAmount = $totalAmount - ($amountPerInstalment * ($numInstalments - 1));
            
            // Crear las cuotas
            for ($i = 1; $i <= $numInstalments; $i++) {
                // Si es la primera cuota, usar la fecha de vencimiento de la factura
                // Para las siguientes, calcular basado en el intervalo
                if ($i === 1) {
                    $dueDate = clone $invoiceDueDate;
                } else {
                    // Calcular la fecha de vencimiento de esta cuota
                    // Basado en la fecha de la cuota anterior + el intervalo
                    $dueDate = (clone $invoiceDueDate)->modify("+" . ($i - 1) * $interval . " days");
                }
                
                // Determinar el monto de esta cuota
                $amount = ($i === $numInstalments) ? $lastInstalmentAmount : $amountPerInstalment;
                
                // Determinar el estado de la cuota basado en la fecha de vencimiento
                $status = 'pending';
                
                // Si la fecha de vencimiento ya pasó, aleatoriamente marcar como pagada o vencida
                if ($dueDate < $currentDate) {
                    // 60% de probabilidad de estar pagada, 40% de estar vencida
                    $status = (rand(1, 100) <= 60) ? 'paid' : 'pending';
                }
                
                // Si es una cuota anterior y la siguiente está pagada, esta también debe estar pagada
                if ($i < $numInstalments && $status === 'pending' && isset($nextStatus) && $nextStatus === 'paid') {
                    $status = 'paid';
                }
                
                // Guardar el estado para la siguiente iteración
                $nextStatus = $status;
                
                // Crear la cuota
                $instalment = [
                    'uuid'            => generate_uuid(),
                    'invoice_id'      => $invoice->id,
                    'number'          => $i,
                    'amount'          => $amount,
                    'due_date'        => $dueDate->format('Y-m-d'),
                    'status'          => $status,
                    'notes'           => 'Cuota generada automáticamente por seeder',
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s')
                ];
                
                try {
                    $this->db->table('instalments')->insert($instalment);
                    $instalmentsCreated++;
                    echo "  Created instalment #{$i} for invoice {$invoiceNumber}, amount: {$amount}, due date: {$dueDate->format('Y-m-d')}, status: {$status}\n";
                } catch (\Exception $e) {
                    echo "  Error creating instalment: " . $e->getMessage() . "\n";
                }
            }
            
            $invoicesProcessed++;
        }
        
        echo "\nInstalment Seeder completed.\n";
        echo "Invoices processed: {$invoicesProcessed}\n";
        echo "Invoices skipped (already had instalments): {$invoicesSkipped}\n";
        echo "Total instalments created: {$instalmentsCreated}\n";
    }
}
