<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run()
    {
        helper('uuid');

        // Get organization
        $organization = $this->db->table('organizations')->get()->getRow();
        if (!$organization) {
            throw new \Exception('No organizations found. Please run OrganizationSeeder first.');
        }

        echo "Using organization ID: {$organization->id}\n";

        // Get all active clients from the organization
        $clients = $this->db->table('clients')
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->get()
            ->getResult();

        if (empty($clients)) {
            throw new \Exception('No active clients found for organization ID: ' . $organization->id);
        }

        echo "Found " . count($clients) . " active clients\n";

        // Current date for reference
        $currentDate = new \DateTime();

        // Concepts for random selection
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

        // For each client, create 1-3 invoices
        foreach ($clients as $client) {
            echo "Creating invoices for client ID: {$client->id}\n";

            // Random number of invoices for this client (1-3)
            $numInvoices = rand(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                // Random amount between 1000 and 5000
                $amount = rand(1000, 5000) + (rand(0, 99) / 100);

                // Random due date between -15 and 45 days from now
                $daysToAdd = rand(-15, 45);
                $dueDate = (clone $currentDate)->modify("$daysToAdd days");

                // Issue date should be before due date
                $issueDate = (clone $dueDate)->modify("-" . rand(5, 30) . " days");

                // Determine status based on due date
                $status = 'pending';
                if ($dueDate < $currentDate) {
                    $status = 'expired';
                }

                // Random concept
                $concept = $concepts[array_rand($concepts)];

                // Obtener el UUID del cliente
                $client_uuid = $this->db->table('clients')
                    ->select('uuid')
                    ->where('id', $client->id)
                    ->get()
                    ->getRow()
                    ->uuid;

                // Generate invoice number
                $invoiceNumber = 'F001-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

                $invoice = [
                    'organization_id' => $organization->id,
                    'client_id'      => $client->id,
                    'client_uuid'    => $client_uuid,
                    'uuid'           => generate_uuid(),
                    'external_id'    => 'EXT-' . strtoupper(bin2hex(random_bytes(4))),
                    'number'         => 'F001-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                    'concept'        => $concept,
                    'total_amount'   => $amount,
                    'currency'       => 'PEN',
                    'issue_date'     => $issueDate->format('Y-m-d'),
                    'due_date'       => $dueDate->format('Y-m-d'),
                    'status'         => $status,
                    'notes'          => 'Factura de prueba generada por seeder',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s')
                ];

                try {
                    $this->db->table('invoices')->insert($invoice);
                    $invoicesCreated++;
                    echo "Created invoice {$invoice['number']} for client {$client->id}\n";
                } catch (\Exception $e) {
                    echo "Error creating invoice for client {$client->id}: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Total invoices created: {$invoicesCreated}\n";
    }
}
