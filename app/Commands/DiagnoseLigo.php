<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagnoseLigo extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:diagnose';
    protected $description = 'Diagnostica el estado de los datos de Ligo en la base de datos';
    protected $usage       = 'ligo:diagnose [options]';
    protected $options = [
        '--org-id' => 'ID específico de organización a diagnosticar'
    ];

    public function run(array $params)
    {
        CLI::write('🔍 Ligo Diagnostic Tool v1.0', 'green');
        CLI::newLine();

        $orgId = CLI::getOption('org-id');
        
        // Obtener organizaciones
        $organizationModel = new \App\Models\OrganizationModel();
        
        if ($orgId) {
            $organizations = [$organizationModel->find($orgId)];
            if (!$organizations[0]) {
                CLI::error("❌ Organización con ID {$orgId} no encontrada");
                return;
            }
        } else {
            $organizations = $organizationModel->findAll();
        }

        CLI::write("📋 Organizaciones encontradas: " . count($organizations), 'green');
        CLI::newLine();

        foreach ($organizations as $org) {
            if (!$org) continue;
            
            CLI::write("🏢 Organización: {$org['name']} (ID: {$org['id']})", 'yellow');
            CLI::write("   UUID: {$org['uuid']}");
            CLI::write("   Ligo Enabled: " . ($org['ligo_enabled'] ? 'Sí' : 'No'));
            CLI::write("   Ligo Environment: " . ($org['ligo_environment'] ?? 'No definido'));
            CLI::newLine();
            
            // Verificar campos legacy
            CLI::write("📊 Campos Legacy (actuales):", 'blue');
            CLI::write("   ligo_username: " . ($org['ligo_username'] ? '✅ ' . $org['ligo_username'] : '❌ Vacío'));
            CLI::write("   ligo_password: " . ($org['ligo_password'] ? '✅ [CONFIGURADO]' : '❌ Vacío'));
            CLI::write("   ligo_company_id: " . ($org['ligo_company_id'] ? '✅ ' . $org['ligo_company_id'] : '❌ Vacío'));
            CLI::write("   ligo_account_id: " . ($org['ligo_account_id'] ? '✅ ' . $org['ligo_account_id'] : '❌ Vacío'));
            CLI::write("   ligo_merchant_code: " . ($org['ligo_merchant_code'] ? '✅ ' . $org['ligo_merchant_code'] : '❌ Vacío'));
            CLI::write("   ligo_private_key: " . ($org['ligo_private_key'] ? '✅ [CONFIGURADO]' : '❌ Vacío'));
            CLI::write("   ligo_webhook_secret: " . ($org['ligo_webhook_secret'] ? '✅ [CONFIGURADO]' : '❌ Vacío'));
            CLI::newLine();
            
            // Verificar si existen las columnas nuevas
            CLI::write("🆕 Campos Separados por Entorno:", 'blue');
            
            // Development fields
            CLI::write("   DEV Fields:");
            CLI::write("      ligo_dev_username: " . (isset($org['ligo_dev_username']) ? ($org['ligo_dev_username'] ? '✅ ' . $org['ligo_dev_username'] : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            CLI::write("      ligo_dev_password: " . (isset($org['ligo_dev_password']) ? ($org['ligo_dev_password'] ? '✅ [CONFIGURADO]' : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            CLI::write("      ligo_dev_company_id: " . (isset($org['ligo_dev_company_id']) ? ($org['ligo_dev_company_id'] ? '✅ ' . $org['ligo_dev_company_id'] : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            
            // Production fields
            CLI::write("   PROD Fields:");
            CLI::write("      ligo_prod_username: " . (isset($org['ligo_prod_username']) ? ($org['ligo_prod_username'] ? '✅ ' . $org['ligo_prod_username'] : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            CLI::write("      ligo_prod_password: " . (isset($org['ligo_prod_password']) ? ($org['ligo_prod_password'] ? '✅ [CONFIGURADO]' : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            CLI::write("      ligo_prod_company_id: " . (isset($org['ligo_prod_company_id']) ? ($org['ligo_prod_company_id'] ? '✅ ' . $org['ligo_prod_company_id'] : '⚪ Existe pero vacío') : '❌ Columna no existe'));
            
            CLI::newLine();
            
            // Verificar estructura de tabla
            $db = \Config\Database::connect();
            $fields = $db->getFieldData('organizations');
            $fieldNames = array_column($fields, 'name');
            
            CLI::write("🗃️  Campos disponibles en la tabla 'organizations':", 'blue');
            $ligoFields = array_filter($fieldNames, function($field) {
                return strpos($field, 'ligo_') === 0;
            });
            
            if (empty($ligoFields)) {
                CLI::write("   ❌ No se encontraron campos de Ligo en la tabla");
            } else {
                foreach ($ligoFields as $field) {
                    CLI::write("   ✅ " . $field);
                }
            }
            
            CLI::newLine(2);
        }
        
        CLI::write('✅ Diagnóstico completado', 'green');
    }
}