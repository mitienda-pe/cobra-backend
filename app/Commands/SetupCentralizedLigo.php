<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SetupCentralizedLigo extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'App';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'ligo:setup-centralized';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Setup centralized Ligo configuration from existing organization data';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'ligo:setup-centralized [--environment=] [--source-org-id=]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--environment' => 'Target environment (dev|prod). Default: dev',
        '--source-org-id' => 'Organization ID to copy credentials from. If not provided, will scan for the first organization with valid credentials.',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $environment = CLI::getOption('environment') ?? 'dev';
        $sourceOrgId = CLI::getOption('source-org-id');

        CLI::write('Setting up centralized Ligo configuration...', 'yellow');
        CLI::write("Target environment: {$environment}");

        $superadminConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $organizationModel = new \App\Models\OrganizationModel();

        // Check if config already exists
        $existingConfig = $superadminConfigModel->getActiveConfig($environment);
        if ($existingConfig && $superadminConfigModel->isConfigurationComplete($existingConfig)) {
            CLI::write("✓ Centralized Ligo configuration already exists and is complete for {$environment} environment", 'green');
            CLI::write("Account ID: " . ($existingConfig['account_id'] ?? 'Not set'));
            CLI::write("Company ID: " . ($existingConfig['company_id'] ?? 'Not set'));
            CLI::write("Username: " . ($existingConfig['username'] ?? 'Not set'));
            return;
        }

        // Find source organization
        $sourceOrg = null;
        if ($sourceOrgId) {
            $sourceOrg = $organizationModel->find($sourceOrgId);
            if (!$sourceOrg) {
                CLI::error("Organization with ID {$sourceOrgId} not found!");
                return;
            }
        } else {
            // Find first organization with valid Ligo credentials
            $organizations = $organizationModel->findAll();
            foreach ($organizations as $org) {
                $prefix = $environment === 'prod' ? 'prod' : 'dev';
                $hasCredentials = !empty($org["ligo_{$prefix}_username"]) && 
                                 !empty($org["ligo_{$prefix}_password"]) && 
                                 !empty($org["ligo_{$prefix}_company_id"]) &&
                                 !empty($org["ligo_{$prefix}_private_key"]);
                
                if ($hasCredentials) {
                    $sourceOrg = $org;
                    break;
                }
            }
        }

        if (!$sourceOrg) {
            CLI::error("No organization found with valid Ligo {$environment} credentials!");
            CLI::write("Please configure Ligo credentials for at least one organization first.");
            return;
        }

        CLI::write("Found source organization: " . $sourceOrg['name'] . " (ID: {$sourceOrg['id']})");

        // Prepare configuration data
        $prefix = $environment === 'prod' ? 'prod' : 'dev';
        $configData = [
            'config_key' => 'ligo_global',
            'environment' => $environment,
            'username' => $sourceOrg["ligo_{$prefix}_username"],
            'password' => $sourceOrg["ligo_{$prefix}_password"],
            'company_id' => $sourceOrg["ligo_{$prefix}_company_id"],
            'account_id' => $sourceOrg["ligo_{$prefix}_account_id"] ?? '',
            'merchant_code' => $sourceOrg["ligo_{$prefix}_merchant_code"] ?? '',
            'private_key' => $sourceOrg["ligo_{$prefix}_private_key"],
            'webhook_secret' => $sourceOrg["ligo_{$prefix}_webhook_secret"] ?? '',
            'enabled' => true,
            'is_active' => true,
            'notes' => "Migrated from organization: {$sourceOrg['name']} (ID: {$sourceOrg['id']}) on " . date('Y-m-d H:i:s')
        ];

        // Save or update configuration
        if ($existingConfig) {
            CLI::write("Updating existing configuration...");
            $superadminConfigModel->update($existingConfig['id'], $configData);
        } else {
            CLI::write("Creating new configuration...");
            $superadminConfigModel->insert($configData);
        }

        CLI::write("✓ Centralized Ligo configuration setup completed!", 'green');
        CLI::write("Environment: {$environment}");
        CLI::write("Username: " . $configData['username']);
        CLI::write("Company ID: " . $configData['company_id']);
        CLI::write("Account ID: " . ($configData['account_id'] ?: 'Not set'));
        CLI::write("Source: {$sourceOrg['name']} (ID: {$sourceOrg['id']})");
        
        CLI::newLine();
        CLI::write("Note: The centralized configuration is now active. All Ligo operations will use these credentials.", 'yellow');
        CLI::write("Organizations can still generate QR codes and perform operations, but using the centralized account.", 'yellow');
    }
}
