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

    public function run(array $params)
    {
        CLI::write('🔐 Ligo Credentials Manager v1.0', 'green');
        CLI::newLine();

        $action = $params[0] ?? 'show';
        
        switch ($action) {
            case 'generate-key':
                $this->generateRSAKey();
                break;
            case 'show':
                $this->showCredentials();
                break;
            default:
                CLI::error("❌ Acción inválida: {$action}");
                CLI::write("Acciones disponibles: show, generate-key");
                return;
        }
    }

    private function generateRSAKey()
    {
        CLI::write('🔧 Generar par de llaves RSA', 'yellow');
        CLI::newLine();

        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        CLI::write('🔐 Generando par de llaves RSA (2048 bits)...');

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($resource);
        $publicKey = $publicKeyDetails['key'];

        $timestamp = date('Y-m-d_H-i-s');
        $privateKeyFile = WRITEPATH . "keys/ligo_private_key_{$timestamp}.pem";
        $publicKeyFile = WRITEPATH . "keys/ligo_public_key_{$timestamp}.pem";

        if (!is_dir(WRITEPATH . 'keys')) {
            mkdir(WRITEPATH . 'keys', 0700, true);
        }

        file_put_contents($privateKeyFile, $privateKey);
        file_put_contents($publicKeyFile, $publicKey);

        CLI::write('✅ Llaves generadas exitosamente:', 'green');
        CLI::write("   • Llave privada: {$privateKeyFile}");
        CLI::write("   • Llave pública: {$publicKeyFile}");
    }

    private function showCredentials()
    {
        $organizationModel = new \App\Models\OrganizationModel();
        $organizations = $organizationModel->findAll();
        
        CLI::write('📋 Organizaciones disponibles:', 'yellow');
        foreach ($organizations as $org) {
            $status = $org['ligo_enabled'] ? '✅' : '❌';
            CLI::write("   {$org['id']}. {$status} {$org['name']}");
        }
    }
}