<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use App\Models\InstalmentModel;

class MigratePaymentsToInstalments extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'migrate:payments_to_instalments';
    protected $description = 'Migra los pagos existentes a cuotas únicas por factura';

    public function run(array $params)
    {
        $this->migrateExistingPayments();
    }

    public function migrateExistingPayments()
    {
        $db = \Config\Database::connect();
        $paymentModel = new PaymentModel();
        $invoiceModel = new InvoiceModel();
        $instalmentModel = new InstalmentModel();

        CLI::write('Iniciando migración de pagos existentes a cuotas...', 'yellow');

        // Obtener todas las facturas con pagos
        $invoicesWithPayments = $db->query(
            "SELECT DISTINCT invoice_id FROM payments WHERE deleted_at IS NULL"
        )->getResultArray();

        CLI::write('Se encontraron ' . count($invoicesWithPayments) . ' facturas con pagos.', 'green');

        $migratedCount = 0;
        $errorCount = 0;

        foreach ($invoicesWithPayments as $row) {
            $invoiceId = $row['invoice_id'];
            
            // Crear una única cuota para esta factura
            $invoice = $invoiceModel->find($invoiceId);
            if (!$invoice) {
                CLI::write('Error: No se encontró la factura con ID ' . $invoiceId, 'red');
                $errorCount++;
                continue;
            }

            try {
                // Iniciar transacción
                $db->transStart();

                // Verificar si ya existe una cuota para esta factura
                $existingInstalment = $instalmentModel->where('invoice_id', $invoiceId)->first();
                
                if ($existingInstalment) {
                    CLI::write('La factura #' . $invoice['invoice_number'] . ' (ID: ' . $invoiceId . ') ya tiene cuotas. Actualizando pagos...', 'yellow');
                    $instalmentId = $existingInstalment['id'];
                } else {
                    // Crear la cuota
                    $instalmentData = [
                        'invoice_id' => $invoiceId,
                        'number' => 1,
                        'amount' => $invoice['amount'],
                        'due_date' => $invoice['due_date'],
                        'status' => $invoice['status'] === 'paid' ? 'paid' : 'pending'
                    ];
                    
                    $instalmentId = $instalmentModel->insert($instalmentData);
                    CLI::write('Creada cuota única para factura #' . $invoice['invoice_number'] . ' (ID: ' . $invoiceId . ')', 'green');
                }
                
                // Actualizar todos los pagos para esta factura
                $result = $db->query(
                    "UPDATE payments SET instalment_id = ? WHERE invoice_id = ? AND deleted_at IS NULL",
                    [$instalmentId, $invoiceId]
                );
                
                $affectedRows = $db->affectedRows();
                CLI::write('Actualizados ' . $affectedRows . ' pagos para la factura #' . $invoice['invoice_number'], 'green');
                
                $db->transComplete();
                
                if ($db->transStatus() === false) {
                    throw new \Exception('Error en la transacción');
                }
                
                $migratedCount++;
            } catch (\Exception $e) {
                CLI::write('Error al migrar la factura #' . $invoice['invoice_number'] . ': ' . $e->getMessage(), 'red');
                $errorCount++;
            }
        }

        CLI::write('Migración completada.', 'green');
        CLI::write('Facturas migradas: ' . $migratedCount, 'green');
        CLI::write('Errores: ' . $errorCount, 'yellow');
    }
}
