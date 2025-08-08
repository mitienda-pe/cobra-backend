<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestLigoConnection extends BaseCommand
{
    protected $group       = 'ligo';
    protected $name        = 'ligo:test-connection';
    protected $description = 'Prueba la conectividad con las APIs de Ligo desde el servidor';
    protected $usage       = 'ligo:test-connection [options]';
    protected $options = [
        '--org-id' => 'ID de organización específica (por defecto usa la primera habilitada)',
        '--env' => 'Entorno a probar: dev o prod (por defecto: dev)',
        '--verbose' => 'Mostrar información detallada'
    ];

    public function run(array $params)
    {
        CLI::write('🌐 Ligo Connection Test v1.0', 'green');
        CLI::newLine();

        $orgId = CLI::getOption('org-id');
        $environment = CLI::getOption('env') ?? 'dev';
        $verbose = CLI::getOption('verbose');
        
        if (!in_array($environment, ['dev', 'prod'])) {
            CLI::error('❌ Entorno inválido. Usa: dev o prod');
            return;
        }

        // Obtener organización
        $organizationModel = new \App\Models\OrganizationModel();
        
        if ($orgId) {
            $organization = $organizationModel->find($orgId);
            if (!$organization) {
                CLI::error("❌ Organización con ID {$orgId} no encontrada");
                return;
            }
        } else {
            $organization = $organizationModel->where('ligo_enabled', 1)->first();
            if (!$organization) {
                CLI::error('❌ No se encontró ninguna organización con Ligo habilitado');
                return;
            }
        }

        CLI::write("🏢 Probando conectividad para: {$organization['name']} (ID: {$organization['id']})", 'yellow');
        CLI::write("🌍 Entorno: " . strtoupper($environment));
        CLI::newLine();

        // Obtener credenciales según el entorno
        $prefix = $environment === 'prod' ? 'prod' : 'dev';
        $credentials = [
            'username' => $organization["ligo_{$prefix}_username"] ?? $organization['ligo_username'] ?? null,
            'password' => $organization["ligo_{$prefix}_password"] ?? $organization['ligo_password'] ?? null,
            'company_id' => $organization["ligo_{$prefix}_company_id"] ?? $organization['ligo_company_id'] ?? null,
            'private_key' => $organization["ligo_{$prefix}_private_key"] ?? $organization['ligo_private_key'] ?? null,
        ];

        // Verificar credenciales
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            CLI::error('❌ Credenciales incompletas para el entorno ' . strtoupper($environment));
            CLI::write("   Username: " . ($credentials['username'] ? '✅' : '❌'));
            CLI::write("   Password: " . ($credentials['password'] ? '✅' : '❌'));
            CLI::write("   Company ID: " . ($credentials['company_id'] ? '✅' : '❌'));
            return;
        }

        CLI::write("✅ Credenciales encontradas para entorno " . strtoupper($environment));
        if ($verbose) {
            CLI::write("   Username: {$credentials['username']}");
            CLI::write("   Company ID: {$credentials['company_id']}");
            CLI::write("   Private Key: " . ($credentials['private_key'] ? 'Configurada' : 'No configurada'));
        }
        CLI::newLine();

        // URLs según el entorno
        $authUrl = "https://cce-auth-{$environment}.ligocloud.tech/v1/auth/sign-in";
        $apiUrl = "https://cce-api-gateway-{$environment}.ligocloud.tech/v1/createQr";
        
        CLI::write("🔗 URLs a probar:");
        CLI::write("   Auth URL: {$authUrl}");
        CLI::write("   API URL: {$apiUrl}");
        CLI::newLine();

        // Test 1: Resolver DNS
        CLI::write("🔍 Test 1: Resolución DNS", 'blue');
        $authHost = parse_url($authUrl, PHP_URL_HOST);
        $apiHost = parse_url($apiUrl, PHP_URL_HOST);
        
        $authIP = gethostbyname($authHost);
        $apiIP = gethostbyname($apiHost);
        
        if ($authIP !== $authHost) {
            CLI::write("   ✅ Auth DNS: {$authHost} → {$authIP}", 'green');
        } else {
            CLI::write("   ❌ Auth DNS: No se pudo resolver {$authHost}", 'red');
        }
        
        if ($apiIP !== $apiHost) {
            CLI::write("   ✅ API DNS: {$apiHost} → {$apiIP}", 'green');
        } else {
            CLI::write("   ❌ API DNS: No se pudo resolver {$apiHost}", 'red');
        }
        CLI::newLine();

        // Test 2: Conectividad básica (ping HTTP)
        CLI::write("🌐 Test 2: Conectividad HTTP básica", 'blue');
        
        foreach ([$authUrl => 'Auth', $apiUrl => 'API'] as $url => $name) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_NOBODY => true, // Solo HEAD request
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                CLI::write("   ❌ {$name}: Error de conexión - {$error}", 'red');
            } else {
                CLI::write("   ✅ {$name}: HTTP {$httpCode}", 'green');
            }
        }
        CLI::newLine();

        // Test 3: Autenticación con Ligo
        CLI::write("🔐 Test 3: Autenticación con Ligo", 'blue');
        
        try {
            // Preparar token JWT si hay private key
            $authorizationToken = null;
            if (!empty($credentials['private_key'])) {
                try {
                    $privateKey = \App\Libraries\JwtGenerator::formatPrivateKey($credentials['private_key']);
                    $payload = ['companyId' => $credentials['company_id']];
                    $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $privateKey, [
                        'issuer' => 'ligo',
                        'audience' => 'ligo-calidad.com',
                        'subject' => 'ligo@gmail.com',
                        'expiresIn' => 3600
                    ]);
                    CLI::write("   ✅ Token JWT generado correctamente");
                } catch (\Exception $e) {
                    CLI::write("   ❌ Error generando token JWT: " . $e->getMessage(), 'red');
                    $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxMzkwNDEsImV4cCI6MTc0NDE0MjY0MSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.chWrhOkQXo2Yc9mOhB8kIHbSmQECtA_PxTsSCcOTCC6OJs7IkDAyj3vkISW7Sm6G88R3KXgxSWhPT4QmShw3xV9a4Jl0FTBQy2KRdTCzbTgRifs9GN0X5KR7KhfChnDSKNosnVQD9QrqTCdlqpvW75vO1rWfTRSXpMtKZRUvy6fPyESv2QxERlo-441e2EwwCly1kgLftpTcMa0qCr-OplD4Iv_YaOw-J5IPAdYqkVPqHQQZO2LCLjP-Q51KPW04VtTyf7UbO6g4OvUb6a423XauAhUFtSw0oGZS11hAYOPSIKO0w6JERLOvJr48lKaouogf0g_M18nZeSDPMZwCWw';
                    CLI::write("   ⚠️  Usando token de fallback para prueba");
                }
            }

            $authData = [
                'username' => $credentials['username'],
                'password' => $credentials['password']
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $authUrl . '?companyId=' . $credentials['company_id'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($authData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authorizationToken
                ],
                CURLOPT_SSL_VERIFYPEER => $environment === 'prod',
                CURLOPT_SSL_VERIFYHOST => $environment === 'prod' ? 2 : 0,
                CURLOPT_FOLLOWLOCATION => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                CLI::write("   ❌ Error CURL: {$error}", 'red');
            } else {
                CLI::write("   📡 HTTP Status: {$httpCode}");
                
                if ($verbose) {
                    CLI::write("   📄 Respuesta completa:");
                    CLI::write(substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
                }

                $decoded = json_decode($response, true);
                if ($decoded) {
                    if (isset($decoded['data']['access_token'])) {
                        CLI::write("   ✅ Autenticación exitosa - Token recibido", 'green');
                    } elseif (isset($decoded['message'])) {
                        CLI::write("   ❌ Error de autenticación: " . $decoded['message'], 'red');
                    } else {
                        CLI::write("   ⚠️  Respuesta inesperada de la API", 'yellow');
                    }
                } else {
                    CLI::write("   ❌ Respuesta no es JSON válido", 'red');
                }
            }

        } catch (\Exception $e) {
            CLI::write("   💥 Excepción: " . $e->getMessage(), 'red');
        }

        CLI::newLine();
        CLI::write('✅ Diagnóstico completado', 'green');
    }
}