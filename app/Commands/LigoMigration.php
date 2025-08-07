<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class LigoMigration extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:migrate';
    protected $description = 'Migra las configuraciones de Ligo entre entornos (dev/prod)';
    protected $usage       = 'ligo:migrate [environment] [options]';
    protected $arguments   = [
        'environment' => 'Entorno objetivo: dev o prod'
    ];
    protected $options = [
        '--org-id'     => 'ID específico de organización a migrar',
        '--dry-run'    => 'Simular la migración sin aplicar cambios',
        '--force'      => 'Forzar migración sin confirmación'
    ];

    public function run(array $params)
    {
        CLI::write('🚀 Ligo Migration Tool v1.0', 'green');
        CLI::newLine();

        $environment = $params[0] ?? null;
        if (!in_array($environment, ['dev', 'prod'])) {
            CLI::error('❌ Entorno inválido. Usa: dev o prod');
            return;
        }

        $orgId = CLI::getOption('org-id');
        $dryRun = CLI::getOption('dry-run');
        $force = CLI::getOption('force');

        CLI::write("📋 Configuración:", 'yellow');
        CLI::write("   • Entorno objetivo: {$environment}");
        CLI::write("   • Organización: " . ($orgId ? "ID {$orgId}" : "Todas"));
        CLI::write("   • Modo: " . ($dryRun ? "Simulación" : "Aplicar cambios"));
        CLI::newLine();

        // Obtener organizaciones
        $organizationModel = new \App\Models\OrganizationModel();
        
        if ($orgId) {
            $organization = $organizationModel->find($orgId);
            if (!$organization) {
                CLI::error("❌ Organización con ID {$orgId} no encontrada");
                return;
            }
            $organizations = [$organization];
        } else {
            $organizations = $organizationModel->where('ligo_enabled', 1)->findAll();
        }

        if (empty($organizations)) {
            CLI::write('⚠️  No se encontraron organizaciones con Ligo habilitado', 'yellow');
            return;
        }

        CLI::write("🔍 Organizaciones encontradas: " . count($organizations), 'green');
        CLI::newLine();

        // Procesar cada organización
        $updated = 0;
        $errors = 0;

        foreach ($organizations as $org) {
            CLI::write("📦 Procesando: {$org['name']} (ID: {$org['id']})");
            
            try {
                $updateData = [
                    'ligo_environment' => $environment,
                    'ligo_ssl_verify' => $environment === 'prod' ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if ($dryRun) {
                    CLI::write("   📋 Simulación: Migración a {$environment} preparada", 'blue');
                    $updated++;
                } else {
                    $result = $organizationModel->update($org['id'], $updateData);
                    
                    if ($result) {
                        CLI::write("   ✅ Migrada a {$environment} exitosamente", 'green');
                        $updated++;
                        
                        // Log de auditoría
                        log_message('info', "LIGO MIGRATION: Organización {$org['name']} (ID: {$org['id']}) migrada a {$environment}");
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
        CLI::write("   • Actualizadas: {$updated}");
        CLI::write("   • Errores: {$errors}");
        
        if ($dryRun) {
            CLI::write("   • Modo simulación - No se aplicaron cambios", 'blue');
        }
        
        CLI::newLine();
        CLI::write($errors === 0 ? '🎉 Migración completada exitosamente!' : '⚠️  Migración completada con errores', $errors === 0 ? 'green' : 'yellow');
    }
}