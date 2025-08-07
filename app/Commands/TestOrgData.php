<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestOrgData extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:test-org-data';
    protected $description = 'Muestra exactamente qué datos se están pasando al formulario';
    protected $usage       = 'ligo:test-org-data [org-id]';

    public function run(array $params)
    {
        CLI::write('🔍 Test Organization Data v1.0', 'green');
        CLI::newLine();

        $orgId = $params[0] ?? 3; // Default to your org
        
        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($orgId);
        
        if (!$organization) {
            CLI::error("❌ Organización con ID {$orgId} no encontrada");
            return;
        }
        
        CLI::write("🏢 Organización: {$organization['name']} (ID: {$organization['id']})", 'yellow');
        CLI::newLine();
        
        CLI::write("📋 TODOS los datos de la organización:", 'blue');
        foreach ($organization as $field => $value) {
            if (strpos($field, 'ligo_') === 0) {
                $displayValue = $value;
                if (in_array($field, ['ligo_password', 'ligo_dev_password', 'ligo_prod_password', 'ligo_private_key', 'ligo_dev_private_key', 'ligo_prod_private_key'])) {
                    $displayValue = $value ? '[TIENE VALOR: ' . strlen($value) . ' chars]' : '[VACÍO]';
                }
                CLI::write("   {$field}: {$displayValue}");
            }
        }
        
        CLI::newLine();
        CLI::write("🎯 Lo que debería aparecer en el formulario:", 'green');
        CLI::write("   ligo_dev_username input value: '" . ($organization['ligo_dev_username'] ?? '') . "'");
        CLI::write("   ligo_dev_password input value: '" . ($organization['ligo_dev_password'] ?? '') . "'");  
        CLI::write("   ligo_dev_company_id input value: '" . ($organization['ligo_dev_company_id'] ?? '') . "'");
        
        // Test the old() function
        CLI::newLine();
        CLI::write("🧪 Test de la función old():", 'blue');
        CLI::write("   old('ligo_dev_username', \$organization['ligo_dev_username'] ?? '') = '" . 
                  (function_exists('old') ? 'old() no disponible en CLI' : 'N/A') . "'");
        CLI::write("   Fallback directo: '" . ($organization['ligo_dev_username'] ?? '') . "'");
        
        CLI::newLine();
        CLI::write('✅ Test completado', 'green');
    }
}