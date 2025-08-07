<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MigrateLigoData extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:migrate-data';
    protected $description = 'Migra los datos existentes de Ligo a los nuevos campos separados dev/prod';
    protected $usage       = 'ligo:migrate-data [options]';
    protected $options = [
        '--dry-run' => 'Simular la migración sin aplicar cambios',
        '--force'   => 'Forzar migración sin confirmación'
    ];

    public function run(array $params)
    {
        CLI::write('🔄 Ligo Data Migration Tool v1.0', 'green');
        CLI::newLine();

        $dryRun = CLI::getOption('dry-run');
        $force = CLI::getOption('force');

        CLI::write("📋 Configuración:", 'yellow');
        CLI::write("   • Modo: " . ($dryRun ? "Simulación" : "Aplicar cambios"));
        CLI::newLine();

        // Obtener organizaciones con datos de Ligo existentes
        $organizationModel = new \App\Models\OrganizationModel();
        $organizations = $organizationModel->where('ligo_enabled', 1)
                                         ->where('ligo_username IS NOT NULL', null, false)
                                         ->findAll();

        if (empty($organizations)) {
            CLI::write('⚠️  No se encontraron organizaciones con credenciales de Ligo para migrar', 'yellow');
            return;
        }

        CLI::write("🔍 Organizaciones encontradas: " . count($organizations), 'green');
        CLI::newLine();

        if (!$force && !$dryRun) {
            $confirm = CLI::prompt('¿Continuar con la migración de datos?', ['y', 'n']);
            if ($confirm !== 'y') {
                CLI::write('❌ Migración cancelada', 'red');
                return;
            }
        }

        // Procesar cada organización
        $migrated = 0;
        $errors = 0;

        foreach ($organizations as $org) {
            CLI::write("📦 Procesando: {$org['name']} (ID: {$org['id']})");
            
            try {
                // Preparar datos para migración
                $updateData = [];
                
                // Migrar a campos de desarrollo por defecto
                if (!empty($org['ligo_username'])) {
                    $updateData['ligo_dev_username'] = $org['ligo_username'];
                }
                if (!empty($org['ligo_password'])) {
                    $updateData['ligo_dev_password'] = $org['ligo_password'];
                }
                if (!empty($org['ligo_company_id'])) {
                    $updateData['ligo_dev_company_id'] = $org['ligo_company_id'];
                }
                if (!empty($org['ligo_account_id'])) {
                    $updateData['ligo_dev_account_id'] = $org['ligo_account_id'];
                }
                if (!empty($org['ligo_merchant_code'])) {
                    $updateData['ligo_dev_merchant_code'] = $org['ligo_merchant_code'];
                }
                if (!empty($org['ligo_private_key'])) {
                    $updateData['ligo_dev_private_key'] = $org['ligo_private_key'];
                }
                if (!empty($org['ligo_webhook_secret'])) {
                    $updateData['ligo_dev_webhook_secret'] = $org['ligo_webhook_secret'];
                }

                if (empty($updateData)) {
                    CLI::write("   ⚠️  Sin datos para migrar", 'yellow');
                    continue;
                }

                $updateData['updated_at'] = date('Y-m-d H:i:s');

                if ($dryRun) {
                    CLI::write("   📋 Simulación: " . count($updateData) . " campos serían migrados a dev", 'blue');
                    $migrated++;
                } else {
                    $result = $organizationModel->update($org['id'], $updateData);
                    
                    if ($result) {
                        CLI::write("   ✅ Datos migrados a campos de desarrollo (" . count($updateData) . " campos)", 'green');
                        $migrated++;
                        
                        // Log de auditoría
                        log_message('info', "LIGO DATA MIGRATION: Organización {$org['name']} (ID: {$org['id']}) - datos migrados a campos dev");
                    } else {
                        CLI::write("   ❌ Error al actualizar en base de datos", 'red');
                        $errors++;
                    }
                }
                
            } catch (\Exception $e) {
                CLI::write("   💥 Error: " . $e->getMessage(), 'red');
                $errors++;
            }
        }

        CLI::newLine();
        CLI::write("📊 Resumen:", 'yellow');
        CLI::write("   • Migradas: {$migrated}");
        CLI::write("   • Errores: {$errors}");
        
        if ($dryRun) {
            CLI::write("   • Modo simulación - No se aplicaron cambios", 'blue');
        } else {
            CLI::write("   • Los datos originales se mantienen como respaldo", 'blue');
        }
        
        CLI::newLine();
        CLI::write($errors === 0 ? '🎉 Migración de datos completada exitosamente!' : '⚠️  Migración completada con errores', $errors === 0 ? 'green' : 'yellow');
        
        if (!$dryRun && $errors === 0) {
            CLI::newLine();
            CLI::write('💡 Recomendación: Verifica que los datos se muestren correctamente en el formulario', 'blue');
        }
    }
}