<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OrganizationModel;
use App\Models\PortfolioModel;

class EnsureOrganizationPortfolios extends BaseCommand
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
    protected $name = 'organizations:ensure-portfolios';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Ensures each organization has at least one default portfolio.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'organizations:ensure-portfolios';

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
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $orgModel = new OrganizationModel();
        $portfolioModel = new PortfolioModel();
        
        $organizations = $orgModel->findAll();
        
        if (empty($organizations)) {
            CLI::write('No organizations found in the database.', 'yellow');
            return;
        }
        
        CLI::write("Found " . count($organizations) . " organizations.", 'green');
        
        foreach ($organizations as $organization) {
            CLI::write("Checking portfolios for organization: " . $organization['name'], 'cyan');
            
            // Count existing portfolios
            $existingPortfolios = $portfolioModel->where('organization_id', $organization['id'])->countAllResults();
            
            if ($existingPortfolios > 0) {
                CLI::write("Organization already has " . $existingPortfolios . " portfolios.", 'green');
                continue;
            }
            
            // Create default portfolio
            $data = [
                'organization_id' => $organization['id'],
                'name' => 'Cartera Empresarial',
                'description' => 'Clientes corporativos y empresas B2B',
                'status' => 'active',
            ];
            
            $portfolioId = $portfolioModel->insert($data);
            
            if ($portfolioId) {
                CLI::write("Created default portfolio (ID: " . $portfolioId . ") for organization.", 'green');
            } else {
                CLI::write("Failed to create default portfolio for organization.", 'red');
            }
        }
        
        CLI::write("Completed portfolio check for all organizations.", 'green');
    }
}