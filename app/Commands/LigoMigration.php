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

        // Obtener organizaciones
        $organizationModel = new \App\Models\OrganizationModel();
        
        if ($orgId) {
            $organizations = [$organizationModel->find($orgId)];
            if (!$organizations[0]) {
                CLI::error("❌ Organización con ID {$orgId} no encontrada");
                return;
            }
        } else {
            $organizations = $organizationModel->where('ligo_enabled', 1)->findAll();
        }

        // Procesar cada organización
        $updated = 0;
        foreach ($organizations as $org) {
            CLI::write("📦 Procesando: {$org['name']} (ID: {$org['id']})");
            
            $updateData = [
                'ligo_environment' => $environment,
                'ligo_ssl_verify' => $environment === 'prod' ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (!$dryRun) {
                $organizationModel->update($org['id'], $updateData);
                CLI::write("   ✅ Migrada a {$environment}", 'green');
                $updated++;
            } else {
                CLI::write("   📋 Simulación: Migración a {$environment} preparada");
            }
        }

        CLI::newLine();
        CLI::write("📊 Resumen: Actualizadas: {$updated}");
    }
}