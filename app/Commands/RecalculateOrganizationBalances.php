<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OrganizationBalanceModel;
use App\Models\OrganizationModel;

class RecalculateOrganizationBalances extends BaseCommand
{
    protected $group       = 'Organization';
    protected $name        = 'org:recalculate-balances';
    protected $description = 'Recalculate balances for all organizations or a specific organization';

    protected $usage = 'org:recalculate-balances [options]';
    protected $arguments = [];
    protected $options = [
        '--organization-id' => 'Specific organization ID to recalculate (optional)',
        '--currency'        => 'Currency to recalculate (PEN, USD, or both). Default: both',
        '--force'          => 'Force recalculation even if already calculated today',
        '--verbose'        => 'Show detailed output'
    ];

    public function run(array $params)
    {
        $organizationId = CLI::getOption('organization-id');
        $currency = CLI::getOption('currency') ?: 'both';
        $force = CLI::getOption('force');
        $verbose = CLI::getOption('verbose');

        // Validate currency option
        $validCurrencies = ['PEN', 'USD', 'both'];
        if (!in_array($currency, $validCurrencies)) {
            CLI::error('Invalid currency. Use: PEN, USD, or both');
            return;
        }

        $organizationModel = new OrganizationModel();
        $balanceModel = new OrganizationBalanceModel();

        CLI::write('Starting organization balance recalculation...', 'green');
        CLI::newLine();

        try {
            if ($organizationId) {
                // Recalculate specific organization
                $organization = $organizationModel->find($organizationId);
                
                if (!$organization) {
                    CLI::error("Organization with ID {$organizationId} not found");
                    return;
                }

                CLI::write("Recalculating balance for: {$organization['name']} (ID: {$organizationId})");
                $this->recalculateOrganization($balanceModel, $organizationId, $organization['name'], $currency, $force, $verbose);
            } else {
                // Recalculate all organizations
                $organizations = $organizationModel->findAll();
                
                if (empty($organizations)) {
                    CLI::write('No organizations found to process.', 'yellow');
                    return;
                }

                CLI::write("Found " . count($organizations) . " organizations to process");
                CLI::newLine();

                foreach ($organizations as $org) {
                    $this->recalculateOrganization($balanceModel, $org['id'], $org['name'], $currency, $force, $verbose);
                }
            }

            CLI::newLine();
            CLI::write('Balance recalculation completed successfully!', 'green');

        } catch (\Exception $e) {
            CLI::error('Error during recalculation: ' . $e->getMessage());
            log_message('error', 'Balance recalculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate balance for a specific organization
     */
    private function recalculateOrganization($balanceModel, $organizationId, $organizationName, $currency, $force, $verbose)
    {
        $currencies = $currency === 'both' ? ['PEN', 'USD'] : [$currency];
        
        foreach ($currencies as $curr) {
            try {
                // Check if already calculated today (unless forced)
                if (!$force) {
                    $existingBalance = $balanceModel->getBalance($organizationId, $curr);
                    if ($existingBalance && $existingBalance['last_calculated_at']) {
                        $calculatedDate = date('Y-m-d', strtotime($existingBalance['last_calculated_at']));
                        $today = date('Y-m-d');
                        
                        if ($calculatedDate === $today) {
                            if ($verbose) {
                                CLI::write("  - {$curr}: Already calculated today, skipping (use --force to override)", 'yellow');
                            }
                            continue;
                        }
                    }
                }

                // Recalculate balance
                $startTime = microtime(true);
                $balanceModel->calculateBalance($organizationId, $curr);
                $endTime = microtime(true);
                
                $executionTime = round(($endTime - $startTime) * 1000, 2);
                
                // Get updated balance for display
                $updatedBalance = $balanceModel->getBalance($organizationId, $curr);
                
                if ($verbose || CLI::getOption('organization-id')) {
                    $totalCollected = $updatedBalance ? number_format($updatedBalance['total_collected'], 2) : '0.00';
                    $ligoPayments = $updatedBalance ? number_format($updatedBalance['total_ligo_payments'], 2) : '0.00';
                    
                    CLI::write("  ✓ {$organizationName} ({$curr}):");
                    CLI::write("    Total collected: {$curr} {$totalCollected}");
                    CLI::write("    Ligo payments: {$curr} {$ligoPayments}");
                    CLI::write("    Execution time: {$executionTime}ms");
                } else {
                    CLI::showProgress();
                }

            } catch (\Exception $e) {
                CLI::error("  ✗ Failed to recalculate {$organizationName} ({$curr}): " . $e->getMessage());
                log_message('error', "Balance recalculation failed for org {$organizationId} ({$curr}): " . $e->getMessage());
            }
        }
        
        if (!$verbose && !CLI::getOption('organization-id')) {
            CLI::write(" ✓ {$organizationName}");
        }
    }

    /**
     * Show summary of all organization balances
     */
    public function showSummary(array $params)
    {
        $currency = CLI::getOption('currency') ?: 'PEN';
        
        if (!in_array($currency, ['PEN', 'USD'])) {
            CLI::error('Invalid currency. Use: PEN or USD');
            return;
        }

        $organizationModel = new OrganizationModel();
        $balanceModel = new OrganizationBalanceModel();

        CLI::write("Organization Balance Summary ({$currency})", 'green');
        CLI::write(str_repeat('=', 60));
        CLI::newLine();

        $organizations = $organizationModel->findAll();
        $totalCollected = 0;
        $totalLigo = 0;
        $totalPending = 0;

        CLI::table(['Organization', 'Total Collected', 'Ligo Payments', 'Pending', 'Last Update'], 
            array_map(function($org) use ($balanceModel, $currency, &$totalCollected, &$totalLigo, &$totalPending) {
                $balance = $balanceModel->getBalance($org['id'], $currency);
                
                $collected = $balance ? $balance['total_collected'] : 0;
                $ligo = $balance ? $balance['total_ligo_payments'] : 0;
                $pending = $balance ? $balance['total_pending'] : 0;
                $lastUpdate = $balance ? date('d/m/Y H:i', strtotime($balance['last_calculated_at'])) : 'Never';
                
                $totalCollected += $collected;
                $totalLigo += $ligo;
                $totalPending += $pending;
                
                return [
                    substr($org['name'], 0, 20),
                    $currency . ' ' . number_format($collected, 2),
                    $currency . ' ' . number_format($ligo, 2),
                    $currency . ' ' . number_format($pending, 2),
                    $lastUpdate
                ];
            }, $organizations)
        );

        CLI::newLine();
        CLI::write("TOTALS ({$currency}):", 'yellow');
        CLI::write("Total Collected: {$currency} " . number_format($totalCollected, 2));
        CLI::write("Ligo Payments:   {$currency} " . number_format($totalLigo, 2));
        CLI::write("Total Pending:   {$currency} " . number_format($totalPending, 2));
    }
}