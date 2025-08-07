<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class LigoCredentials extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:credentials';
    protected $description = 'Gestiona las credenciales de Ligo para una organización';
    protected $usage       = 'ligo:credentials [action] [options]';
    protected $arguments   = [
        'action' => 'Acción: set, show, test, generate-key'
    ];
    protected $options = [
        '--org-id'      => 'ID de la organización',
        '--org-name'    => 'Nombre de la organización (alternativa a --org-id)',
        '--username'    => 'Username de Ligo',
        '--password'    => 'Password de Ligo',
        '--company-id'  => 'Company ID de Ligo',
        '--account-id'  => 'Account ID de Ligo',
        '--merchant-code' => 'Merchant Code de Ligo',
        '--private-key' => 'Archivo con la llave privada RSA',
        '--environment' => 'Entorno: dev o prod',
        '--force'       => 'Forzar operación sin confirmación'
    ];

    public function run(array $params)
    {
        CLI::write('🔐 Ligo Credentials Manager v1.0', 'green');
        CLI::newLine();

        $action = $params[0] ?? 'show';
        
        switch ($action) {
            case 'set':
                $this->setCredentials();
                break;
            case 'show':
                $this->showCredentials();
                break;
            case 'test':
                $this->testCredentials();
                break;
            case 'generate-key':
                $this->generateRSAKey();
                break;
            default:
                CLI::error("❌ Acción inválida: {$action}");
                CLI::write("Acciones disponibles: set, show, test, generate-key");
                return;
        }
    }

    private function setCredentials()
    {
        CLI::write('📝 Configurar credenciales de Ligo', 'yellow');
        CLI::newLine();

        // Obtener organización
        $organization = $this->getOrganization();
        if (!$organization) {
            return;
        }

        // Recopilar credenciales
        $credentials = $this->collectCredentials();
        if (!$credentials) {
            return;
        }

        // Mostrar resumen
        CLI::write('📋 Resumen de credenciales:', 'yellow');
        CLI::write("   • Organización: {$organization['name']} (ID: {$organization['id']})");
        CLI::write("   • Username: {$credentials['username']}");
        CLI::write("   • Password: " . str_repeat('*', strlen($credentials['password'])));
        CLI::write("   • Company ID: {$credentials['company_id']}");
        CLI::write("   • Account ID: " . ($credentials['account_id'] ?? 'No especificado'));
        CLI::write("   • Merchant Code: " . ($credentials['merchant_code'] ?? 'No especificado'));
        CLI::write("   • Entorno: " . ($credentials['environment'] ?? 'dev'));
        CLI::write("   • Llave privada: " . ($credentials['private_key'] ? 'Configurada' : 'No configurada'));
        CLI::newLine();

        // Confirmar
        if (!CLI::getOption('force')) {
            if (CLI::prompt('¿Guardar estas credenciales?', ['y', 'n']) !== 'y') {
                CLI::write('❌ Operación cancelada', 'red');
                return;
            }
        }

        // Guardar
        try {
            $organizationModel = new \App\Models\OrganizationModel();
            
            $updateData = [
                'ligo_enabled' => 1,
                'ligo_username' => $credentials['username'],
                'ligo_password' => $credentials['password'],
                'ligo_company_id' => $credentials['company_id'],
                'ligo_environment' => $credentials['environment'] ?? 'dev',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($credentials['account_id']) {
                $updateData['ligo_account_id'] = $credentials['account_id'];
            }
            
            if ($credentials['merchant_code']) {
                $updateData['ligo_merchant_code'] = $credentials['merchant_code'];
            }
            
            if ($credentials['private_key']) {
                $updateData['ligo_private_key'] = $credentials['private_key'];
            }

            $result = $organizationModel->update($organization['id'], $updateData);

            if ($result) {
                CLI::write('✅ Credenciales guardadas exitosamente', 'green');
                
                // Log de auditoría
                log_message('info', "LIGO CREDENTIALS: Credenciales actualizadas para organización {$organization['name']} (ID: {$organization['id']})");
            } else {
                CLI::error('❌ Error al guardar credenciales en la base de datos');
            }

        } catch (\Exception $e) {
            CLI::error('❌ Error: ' . $e->getMessage());
        }
    }

    private function showCredentials()
    {
        CLI::write('👁️  Mostrar credenciales de Ligo', 'yellow');
        CLI::newLine();

        $organization = $this->getOrganization();
        if (!$organization) {
            return;
        }

        CLI::write("📋 Credenciales para: {$organization['name']} (ID: {$organization['id']})", 'green');
        CLI::newLine();

        // Estado general
        $enabled = $organization['ligo_enabled'] ? '✅ Habilitado' : '❌ Deshabilitado';
        CLI::write("Estado: {$enabled}");
        
        // Credenciales básicas
        CLI::write("Username: " . ($organization['ligo_username'] ?? '❌ No configurado'));
        CLI::write("Password: " . ($organization['ligo_password'] ? '✅ Configurado' : '❌ No configurado'));
        CLI::write("Company ID: " . ($organization['ligo_company_id'] ?? '❌ No configurado'));
        
        // Configuración adicional
        CLI::write("Account ID: " . ($organization['ligo_account_id'] ?? '⚠️  No configurado (se usará por defecto)'));
        CLI::write("Merchant Code: " . ($organization['ligo_merchant_code'] ?? '⚠️  No configurado (se usará por defecto)'));
        
        // Entorno
        $environment = $organization['ligo_environment'] ?? 'dev';
        $envIcon = $environment === 'prod' ? '🔴' : '🟡';
        CLI::write("Entorno: {$envIcon} {$environment}");
        
        // URLs
        $authUrl = $organization['ligo_auth_url'] ?? "https://cce-auth-{$environment}.ligocloud.tech";
        $apiUrl = $organization['ligo_api_url'] ?? "https://cce-api-gateway-{$environment}.ligocloud.tech";
        CLI::write("Auth URL: {$authUrl}");
        CLI::write("API URL: {$apiUrl}");
        
        // Llave privada
        CLI::write("Llave privada RSA: " . ($organization['ligo_private_key'] ? '✅ Configurada' : '❌ No configurada'));
        
        // Token status
        if ($organization['ligo_token'] && $organization['ligo_token_expiry']) {
            $expiry = strtotime($organization['ligo_token_expiry']);
            $now = time();
            if ($expiry > $now) {
                $remaining = round(($expiry - $now) / 60);
                CLI::write("Token: ✅ Válido (expira en {$remaining} minutos)");
            } else {
                CLI::write("Token: ⚠️  Expirado");
            }
        } else {
            CLI::write("Token: ❌ No hay token almacenado");
        }

        // Errores recientes
        if ($organization['ligo_auth_error']) {
            CLI::write("Último error: ❌ " . $organization['ligo_auth_error'], 'red');
        }
    }

    private function testCredentials()
    {
        CLI::write('🧪 Probar credenciales de Ligo', 'yellow');
        CLI::newLine();

        $organization = $this->getOrganization();
        if (!$organization) {
            return;
        }

        CLI::write("🔬 Probando credenciales para: {$organization['name']}");
        CLI::newLine();

        // Validaciones básicas
        $errors = [];
        if (empty($organization['ligo_username'])) $errors[] = 'Username no configurado';
        if (empty($organization['ligo_password'])) $errors[] = 'Password no configurado';
        if (empty($organization['ligo_company_id'])) $errors[] = 'Company ID no configurado';
        if (empty($organization['ligo_private_key'])) $errors[] = 'Llave privada no configurada';

        if (!empty($errors)) {
            CLI::write('❌ Validación básica fallida:', 'red');
            foreach ($errors as $error) {
                CLI::write("   • {$error}");
            }
            return;
        }

        CLI::write('✅ Validaciones básicas: OK', 'green');

        // Probar generación de JWT
        try {
            CLI::write('🔑 Probando generación de JWT...');
            
            $privateKey = \App\Libraries\JwtGenerator::formatPrivateKey($organization['ligo_private_key']);
            if (!$privateKey) {
                CLI::error('❌ Llave privada RSA inválida');
                return;
            }
            
            $payload = ['companyId' => $organization['ligo_company_id']];
            $jwt = \App\Libraries\JwtGenerator::generateToken($payload, $privateKey);
            
            CLI::write('✅ Generación de JWT: OK', 'green');
            CLI::write('   Token: ' . substr($jwt, 0, 50) . '...', 'light_gray');

        } catch (\Exception $e) {
            CLI::error('❌ Error generando JWT: ' . $e->getMessage());
            return;
        }

        // Probar autenticación con Ligo
        CLI::write('📡 Probando autenticación con Ligo...');
        
        // Simular llamada de autenticación (sin hacer la petición real)
        $environment = $organization['ligo_environment'] ?? 'dev';
        $authUrl = $organization['ligo_auth_url'] ?? "https://cce-auth-{$environment}.ligocloud.tech";
        $fullAuthUrl = $authUrl . '/v1/auth/sign-in?companyId=' . $organization['ligo_company_id'];
        
        CLI::write("   URL: {$fullAuthUrl}", 'light_gray');
        CLI::write('   Payload: {"username": "' . $organization['ligo_username'] . '", "password": "***"}', 'light_gray');
        CLI::write('   Authorization: Bearer ' . substr($jwt, 0, 30) . '...', 'light_gray');
        
        CLI::write('⚠️  Test de autenticación simulado (no se hizo petición real)', 'yellow');
        CLI::write('✅ Configuración lista para usar', 'green');
    }

    private function generateRSAKey()
    {
        CLI::write('🔧 Generar par de llaves RSA', 'yellow');
        CLI::newLine();

        // Generar par de llaves
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        CLI::write('🔐 Generando par de llaves RSA (2048 bits)...');

        $resource = openssl_pkey_new($config);
        if (!$resource) {
            CLI::error('❌ Error generando llaves RSA');
            return;
        }

        // Exportar llave privada
        openssl_pkey_export($resource, $privateKey);

        // Exportar llave pública
        $publicKeyDetails = openssl_pkey_get_details($resource);
        $publicKey = $publicKeyDetails['key'];

        // Crear archivos
        $timestamp = date('Y-m-d_H-i-s');
        $privateKeyFile = WRITEPATH . "keys/ligo_private_key_{$timestamp}.pem";
        $publicKeyFile = WRITEPATH . "keys/ligo_public_key_{$timestamp}.pem";

        // Crear directorio si no existe
        if (!is_dir(WRITEPATH . 'keys')) {
            mkdir(WRITEPATH . 'keys', 0700, true);
        }

        // Guardar archivos
        file_put_contents($privateKeyFile, $privateKey);
        file_put_contents($publicKeyFile, $publicKey);

        CLI::write('✅ Llaves generadas exitosamente:', 'green');
        CLI::write("   • Llave privada: {$privateKeyFile}");
        CLI::write("   • Llave pública: {$publicKeyFile}");
        CLI::newLine();

        CLI::write('📋 Próximos pasos:', 'yellow');
        CLI::write('1. Envía la llave pública a Ligo Payments para registro');
        CLI::write('2. Configura la llave privada con: php spark ligo:credentials set --private-key ' . $privateKeyFile);
        CLI::write('3. Actualiza las demás credenciales de producción');
        CLI::newLine();

        CLI::write('⚠️  IMPORTANTE: Guarda la llave privada de forma segura', 'red');
    }

    private function getOrganization()
    {
        $orgId = CLI::getOption('org-id');
        $orgName = CLI::getOption('org-name');

        $organizationModel = new \App\Models\OrganizationModel();

        if ($orgId) {
            $organization = $organizationModel->find($orgId);
            if (!$organization) {
                CLI::error("❌ Organización con ID {$orgId} no encontrada");
                return null;
            }
        } elseif ($orgName) {
            $organization = $organizationModel->where('name', $orgName)->first();
            if (!$organization) {
                CLI::error("❌ Organización con nombre '{$orgName}' no encontrada");
                return null;
            }
        } else {
            // Mostrar lista de organizaciones
            $organizations = $organizationModel->findAll();
            if (empty($organizations)) {
                CLI::error('❌ No hay organizaciones registradas');
                return null;
            }

            CLI::write('📋 Organizaciones disponibles:', 'yellow');
            foreach ($organizations as $org) {
                $status = $org['ligo_enabled'] ? '✅' : '❌';
                CLI::write("   {$org['id']}. {$status} {$org['name']}");
            }
            CLI::newLine();

            $selectedId = CLI::prompt('Selecciona el ID de la organización');
            $organization = $organizationModel->find($selectedId);
            
            if (!$organization) {
                CLI::error("❌ Organización con ID {$selectedId} no encontrada");
                return null;
            }
        }

        return $organization;
    }

    private function collectCredentials()
    {
        $credentials = [];

        // Username
        $credentials['username'] = CLI::getOption('username') ?? CLI::prompt('Username de Ligo');
        if (empty($credentials['username'])) {
            CLI::error('❌ Username es requerido');
            return null;
        }

        // Password
        $credentials['password'] = CLI::getOption('password') ?? CLI::prompt('Password de Ligo');
        if (empty($credentials['password'])) {
            CLI::error('❌ Password es requerido');
            return null;
        }

        // Company ID
        $credentials['company_id'] = CLI::getOption('company-id') ?? CLI::prompt('Company ID de Ligo');
        if (empty($credentials['company_id'])) {
            CLI::error('❌ Company ID es requerido');
            return null;
        }

        // Campos opcionales
        $credentials['account_id'] = CLI::getOption('account-id') ?? CLI::prompt('Account ID (opcional, presiona Enter para omitir)', null, null, '');
        $credentials['merchant_code'] = CLI::getOption('merchant-code') ?? CLI::prompt('Merchant Code (opcional, presiona Enter para omitir)', null, null, '');
        
        // Entorno
        $credentials['environment'] = CLI::getOption('environment');
        if (!$credentials['environment']) {
            $credentials['environment'] = CLI::prompt('Entorno', ['dev', 'prod']);
        }

        // Llave privada
        $privateKeyFile = CLI::getOption('private-key');
        if ($privateKeyFile) {
            if (!file_exists($privateKeyFile)) {
                CLI::error("❌ Archivo de llave privada no encontrado: {$privateKeyFile}");
                return null;
            }
            $credentials['private_key'] = file_get_contents($privateKeyFile);
        } elseif (CLI::prompt('¿Configurar llave privada RSA?', ['y', 'n']) === 'y') {
            $privateKeyFile = CLI::prompt('Ruta al archivo de llave privada');
            if ($privateKeyFile && file_exists($privateKeyFile)) {
                $credentials['private_key'] = file_get_contents($privateKeyFile);
            } else {
                CLI::write('⚠️  Llave privada no configurada - puedes configurarla después', 'yellow');
            }
        }

        return $credentials;
    }
}