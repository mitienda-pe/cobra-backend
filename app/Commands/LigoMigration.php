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

        // Validar parámetros
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

        // Confirmar acción (excepto si es dry-run o force)
        if (!$dryRun && !$force) {
            if (!CLI::prompt('¿Continuar con la migración?', ['y', 'n']) === 'y') {
                CLI::write('❌ Migración cancelada', 'red');
                return;
            }
        }

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
                $result = $this->migrateOrganization($org, $environment, $dryRun);
                
                if ($result['success']) {
                    CLI::write("   ✅ " . $result['message'], 'green');
                    $updated++;
                } else {
                    CLI::write("   ❌ " . $result['message'], 'red');
                    $errors++;
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

    private function migrateOrganization($org, $environment, $dryRun = false)
    {
        // Configuraciones por entorno
        $configs = [
            'dev' => [
                'auth_url' => 'https://cce-auth-dev.ligocloud.tech',
                'api_url' => 'https://cce-api-gateway-dev.ligocloud.tech',
                'ssl_verify' => false,
                'description' => 'Desarrollo'
            ],
            'prod' => [
                'auth_url' => 'https://cce-auth-prod.ligocloud.tech',
                'api_url' => 'https://cce-api-gateway-prod.ligocloud.tech',
                'ssl_verify' => true,
                'description' => 'Producción'
            ]
        ];

        $config = $configs[$environment];
        
        // Validaciones pre-migración
        $validations = $this->validateOrganization($org, $environment);
        if (!empty($validations['errors'])) {
            return [
                'success' => false,
                'message' => 'Validación fallida: ' . implode(', ', $validations['errors'])
            ];
        }

        // Preparar datos de actualización
        $updateData = [
            'ligo_auth_url' => $config['auth_url'],
            'ligo_api_url' => $config['api_url'],
            'ligo_environment' => $environment,
            'ligo_ssl_verify' => $config['ssl_verify'],
            'ligo_token' => null, // Limpiar token para forzar nueva autenticación
            'ligo_token_expiry' => null,
            'ligo_auth_error' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($dryRun) {
            return [
                'success' => true,
                'message' => "Simulación: Migración a {$config['description']} preparada"
            ];
        }

        // Aplicar cambios
        $organizationModel = new \App\Models\OrganizationModel();
        $result = $organizationModel->update($org['id'], $updateData);

        if ($result) {
            // Log de auditoría
            log_message('info', "LIGO MIGRATION: Organización {$org['name']} (ID: {$org['id']}) migrada a {$environment}");
            
            return [
                'success' => true,
                'message' => "Migrada a {$config['description']} exitosamente"
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al actualizar en base de datos'
            ];
        }
    }

    private function validateOrganization($org, $environment)
    {
        $errors = [];
        $warnings = [];

        // Validaciones básicas
        if (empty($org['ligo_username'])) {
            $errors[] = 'Username no configurado';
        }
        
        if (empty($org['ligo_password'])) {
            $errors[] = 'Password no configurado';
        }
        
        if (empty($org['ligo_company_id'])) {
            $errors[] = 'Company ID no configurado';
        }

        // Validaciones específicas para producción
        if ($environment === 'prod') {
            if (empty($org['ligo_private_key'])) {
                $errors[] = 'Llave privada RSA requerida para producción';
            } else {
                // Validar formato de llave privada
                $key = \App\Libraries\JwtGenerator::formatPrivateKey($org['ligo_private_key']);
                if (!$key) {
                    $errors[] = 'Llave privada RSA inválida';
                }
            }
            
            if (empty($org['ligo_account_id'])) {
                $warnings[] = 'Account ID no configurado (se usará valor por defecto)';
            }
            
            if (empty($org['ligo_merchant_code'])) {
                $warnings[] = 'Merchant Code no configurado (se usará valor por defecto)';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}