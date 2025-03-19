<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PortfolioModel;

class CleanOldPortfolios extends BaseCommand
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
    protected $name = 'portfolios:clean-old';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Removes old Commercial and Personal portfolios from the system.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'portfolios:clean-old';

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
        $db = \Config\Database::connect();
        
        // First, identify the portfolios to delete
        $builder = $db->table('portfolios');
        $builder->whereIn('name', ['Cartera Comercial', 'Cartera Personal']);
        $oldPortfolios = $builder->get()->getResultArray();
        
        if (empty($oldPortfolios)) {
            CLI::write('No old portfolios found to clean up.', 'yellow');
            return;
        }
        
        CLI::write('Found ' . count($oldPortfolios) . ' old portfolios to remove.', 'yellow');
        
        $portfolioIds = array_column($oldPortfolios, 'id');
        
        // First remove any client associations
        $clientPortfolioTable = $db->table('client_portfolio');
        $clientPortfolioTable->whereIn('portfolio_id', $portfolioIds);
        $deleteResult1 = $clientPortfolioTable->delete();
        CLI::write('Removed ' . $deleteResult1 . ' client assignments from old portfolios.', 'green');
        
        // Then remove any user associations
        $portfolioUserTable = $db->table('portfolio_user');
        $portfolioUserTable->whereIn('portfolio_id', $portfolioIds);
        $deleteResult2 = $portfolioUserTable->delete();
        CLI::write('Removed ' . $deleteResult2 . ' user assignments from old portfolios.', 'green');
        
        // Finally delete the portfolios themselves
        $portfolioTable = $db->table('portfolios');
        $portfolioTable->whereIn('id', $portfolioIds);
        $deleteResult3 = $portfolioTable->delete();
        
        CLI::write('Successfully deleted ' . $deleteResult3 . ' old portfolios!', 'green');
    }
}