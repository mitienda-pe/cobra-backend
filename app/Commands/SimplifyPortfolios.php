<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;
use App\Models\PortfolioModel;

class SimplifyPortfolios extends BaseCommand
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
    protected $name = 'portfolios:simplify';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Ensures each user has exactly one portfolio and cleans up the old structure.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'portfolios:simplify';

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
        $userModel = new UserModel();
        $portfolioModel = new PortfolioModel();
        $db = \Config\Database::connect();
        
        // 1. Get all users with role 'user' (collectors)
        $users = $userModel->where('role', 'user')->findAll();
        
        if (empty($users)) {
            CLI::write('No collector users found in the database.', 'yellow');
            return;
        }
        
        CLI::write("Found " . count($users) . " collector users.", 'green');
        $progress = CLI::showProgress(0, count($users));
        
        $count = 0;
        
        // 2. Process each user
        foreach ($users as $user) {
            // Get current portfolios assigned to this user
            $currentPortfolios = $portfolioModel->getByUser($user['id']);
            
            if (count($currentPortfolios) == 1) {
                // User already has exactly one portfolio - just update its name
                $portfolioId = $currentPortfolios[0]['id'];
                $portfolioName = 'Cartera de ' . $user['name'];
                
                $portfolioModel->update($portfolioId, [
                    'name' => $portfolioName,
                    'description' => 'Cartera única para el cobrador',
                ]);
                
                CLI::write("User already has one portfolio, updated name: " . $portfolioName, 'info');
            } 
            else if (count($currentPortfolios) > 1) {
                // User has multiple portfolios - merge them
                // Keep the first portfolio and assign all clients to it
                $keepPortfolioId = $currentPortfolios[0]['id'];
                $portfolioName = 'Cartera de ' . $user['name'];
                
                // Update the portfolio we're keeping
                $portfolioModel->update($keepPortfolioId, [
                    'name' => $portfolioName,
                    'description' => 'Cartera única para el cobrador',
                ]);
                
                // Get all clients from all portfolios
                $allClientIds = [];
                foreach ($currentPortfolios as $portfolio) {
                    $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                    foreach ($clients as $client) {
                        $allClientIds[$client['id']] = $client['id']; // Use as key to avoid duplicates
                    }
                    
                    // If not the portfolio we're keeping, delete the assignments
                    if ($portfolio['id'] != $keepPortfolioId) {
                        $db->table('portfolio_user')->where('portfolio_id', $portfolio['id'])->delete();
                        $db->table('client_portfolio')->where('portfolio_id', $portfolio['id'])->delete();
                        $portfolioModel->delete($portfolio['id']);
                    }
                }
                
                // Assign all clients to the kept portfolio
                if (!empty($allClientIds)) {
                    $portfolioModel->assignClients($keepPortfolioId, array_values($allClientIds));
                }
                
                CLI::write("Merged " . count($currentPortfolios) . " portfolios into one for user: " . $user['name'], 'info');
            }
            else {
                // User has no portfolios - create one
                $portfolioName = 'Cartera de ' . $user['name'];
                $portfolioData = [
                    'organization_id' => $user['organization_id'],
                    'name' => $portfolioName,
                    'description' => 'Cartera única para el cobrador',
                    'status' => 'active'
                ];
                
                $portfolioId = $portfolioModel->insert($portfolioData);
                
                // Assign user to portfolio
                $db->table('portfolio_user')->insert([
                    'portfolio_id' => $portfolioId,
                    'user_id' => $user['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                
                CLI::write("Created new portfolio for user: " . $user['name'], 'info');
            }
            
            $count++;
            CLI::showProgress($count, count($users));
        }
        
        CLI::showProgress(false);
        
        // 3. Delete any portfolios not assigned to users
        $builder = $db->table('portfolios p');
        $builder->select('p.id');
        $builder->join('portfolio_user pu', 'p.id = pu.portfolio_id', 'left');
        $builder->where('pu.portfolio_id IS NULL');
        $orphanedPortfolios = $builder->get()->getResultArray();
        
        if (!empty($orphanedPortfolios)) {
            $orphanedIds = array_column($orphanedPortfolios, 'id');
            
            foreach ($orphanedIds as $id) {
                $db->table('client_portfolio')->where('portfolio_id', $id)->delete();
                $portfolioModel->delete($id);
            }
            
            CLI::write("Deleted " . count($orphanedIds) . " orphaned portfolios.", 'yellow');
        }
        
        CLI::write("Portfolio simplification completed successfully!", 'green');
    }
}