<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class NewInvoiceSeeder extends Seeder
{
    public function run()
    {
        helper('uuid');

        // Obtener organización
        $organization = $this->db->table('organizations')->get()->getRow();
        if (!$organization) {
            throw new \Exception('No se encontraron organizaciones. Por favor, ejecute OrganizationSeeder primero.');
        }

        echo "Usando organización ID: {$organization->id}\n";

        // Obtener todos los clientes activos de la organización
        $clients = $this->db->table('clients')
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->get()
            ->getResult();

        if (empty($clients)) {
            throw new \Exception('No se encontraron clientes activos para la organización ID: ' . $organization->id);
        }

        echo "Se encontraron " . count($clients) . " clientes activos\n";

        // Fecha actual para referencia
        $currentDate = new \DateTime();

        // Conceptos para selección aleatoria
        $concepts = [
            'Servicio de mantenimiento',
            'Consultoría empresarial',
            'Venta de productos',
            'Servicios profesionales',
            'Alquiler de equipos',
            'Desarrollo de software',
            'Servicio de transporte',
            'Servicios generales'
        ];

        $invoicesCreated = 0;

        // Para cada cliente, crear 1-3 facturas
        foreach ($clients as $client) {
            echo "Creando facturas para cliente ID: {$client->id}\n";

            // Número aleatorio de facturas para este cliente (1-3)
            $numInvoices = rand(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                // Monto aleatorio entre 1000 y 5000
                $amount = rand(1000, 5000) + (rand(0, 99) / 100);

                // Fecha de vencimiento aleatoria entre -15 y 45 días desde ahora
                $daysToAdd = rand(-15, 45);
                $dueDate = (clone $currentDate)->modify("$daysToAdd days");

                // La fecha de emisión debe ser anterior a la fecha de vencimiento
                $issueDate = (clone $dueDate)->modify("-" . rand(5, 30) . " days");

                // Determinar estado basado en la fecha de vencimiento
                $status = 'pending';
                if ($dueDate < $currentDate) {
                    $status = 'expired';
                }

                // Concepto aleatorio
                $concept = $concepts[array_rand($concepts)];

                // Generar número de factura
                $invoiceNumber = 'F001-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

                // Datos del cliente para la factura
                $clientDocumentType = '01'; // DNI por defecto
                $clientDocumentNumber = $client->document_number ?? '12345678';
                $clientName = $client->business_name ?? 'Cliente Sin Nombre';
                $clientAddress = $client->address ?? 'Sin dirección registrada';

                $invoice = [
                    'organization_id' => $organization->id,
                    'client_id' => $client->id,
                    'client_uuid' => $client->uuid,
                    'uuid' => generate_uuid(),
                    'external_id' => 'EXT-' . strtoupper(bin2hex(random_bytes(4))),
                    'number' => $invoiceNumber,
                    'concept' => $concept,
                    'total_amount' => $amount,
                    'currency' => 'PEN',
                    'issue_date' => $issueDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => $status,
                    'notes' => 'Factura de prueba generada por seeder',
                    'document_type' => '01', // Factura
                    'series' => 'F001',
                    'client_document_type' => $clientDocumentType,
                    'client_document_number' => $clientDocumentNumber,
                    'client_name' => $clientName,
                    'client_address' => $clientAddress,
                    'paid_amount' => 0, // Inicialmente no hay pagos
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                try {
                    $this->db->table('invoices')->insert($invoice);
                    $invoicesCreated++;
                    echo "Creada factura {$invoice['number']} para cliente {$client->id}\n";
                } catch (\Exception $e) {
                    echo "Error al crear factura para cliente {$client->id}: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "\nSeeder de facturas completado.\n";
        echo "Total de facturas creadas: {$invoicesCreated}\n";
    }
}
