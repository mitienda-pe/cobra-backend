<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class Debug extends Controller
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
    }
    
    /**
     * Show organization context debug information
     */
    public function orgContext()
    {
        // Only accessible by superadmin for security reasons
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')
                ->with('error', 'No tiene permisos para acceder a esta funcionalidad de depuración.');
        }
        
        // Force refresh organization context
        $currentOrgId = $this->refreshOrganizationContext();
        
        // Get additional debugging info
        $user = $this->auth->user();
        $sessionData = $this->session->get();
        
        // Get all available organizations
        $orgModel = new \App\Models\OrganizationModel();
        $organizations = $orgModel->findAll();
        
        // Get database count of resources by organization
        $db = \Config\Database::connect();
        $stats = [];
        
        // Tables that should have organization_id column
        $tablesWithOrgId = ['clients', 'invoices', 'portfolios'];
        
        // Check if payments table exists and has organization_id column
        try {
            // Just count total payments without assuming organization_id exists
            if ($db->tableExists('payments')) {
                $paymentCount = $db->table('payments')->countAllResults();
                $stats['payments'] = [];
                $stats['payments']['total'] = $paymentCount;
                $stats['payments']['by_org'] = [];
                
                foreach ($organizations as $org) {
                    // For payments, use a different approach since they don't have direct organization_id
                    // Use a join query similar to PaymentModel::getByOrganization
                    $builder = $db->table('payments p');
                    $builder->join('invoices i', 'p.invoice_id = i.id', 'inner');
                    $builder->where('i.organization_id', $org['id']);
                    $builder->where('p.deleted_at IS NULL');
                    $count = $builder->countAllResults();
                    
                    $stats['payments']['by_org'][$org['id']] = $count;
                }
                
                log_message('debug', '[Debug] Payments counted using JOIN with invoices table');
            } else {
                log_message('debug', '[Debug] Payments table does not exist');
            }
        } catch (\Exception $e) {
            // Payments table might not exist yet or structure might be different
            log_message('debug', '[Debug] Error counting payments: ' . $e->getMessage());
            $stats['payments'] = [];
            $stats['payments']['total'] = 0;
            $stats['payments']['by_org'] = [];
            foreach ($organizations as $org) {
                $stats['payments']['by_org'][$org['id']] = 0;
            }
        }
        
        foreach ($tablesWithOrgId as $table) {
            $stats[$table] = [];
            
            try {
                // Overall count
                $stats[$table]['total'] = $db->table($table)->countAllResults();
                
                // Count by organization
                foreach ($organizations as $org) {
                    $count = $db->table($table)
                        ->where('organization_id', $org['id'])
                        ->countAllResults();
                        
                    $stats[$table]['by_org'][$org['id']] = $count;
                }
            } catch (\Exception $e) {
                // Handle case where table doesn't exist or doesn't have organization_id
                log_message('debug', 'Error counting ' . $table . ': ' . $e->getMessage());
                $stats[$table]['total'] = 0;
                foreach ($organizations as $org) {
                    $stats[$table]['by_org'][$org['id']] = 0;
                }
            }
        }
        
        $data = [
            'auth' => $this->auth,
            'current_organization_id' => $currentOrgId,
            'user' => $user,
            'session' => $sessionData,
            'organizations' => $organizations,
            'stats' => $stats,
        ];
        
        // Create a simple debug view directly in the controller
        return $this->renderDebugPage($data);
    }
    
    /**
     * Show CSRF debug information
     */
    public function csrf()
    {
        // Only accessible by superadmin for security reasons
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')
                ->with('error', 'No tiene permisos para acceder a esta funcionalidad de depuración.');
        }
        
        $security = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $tokenValue = $security->getHash();
        
        $data = [
            'auth' => $this->auth,
            'token_name' => $tokenName,
            'token_value' => $tokenValue,
            'session_token' => session()->get($tokenName),
            'cookie_value' => isset($_COOKIE[$tokenName]) ? $_COOKIE[$tokenName] : 'Not found',
            'request_headers' => $_SERVER,
            'request_method' => $_SERVER['REQUEST_METHOD'],
        ];
        
        return $this->renderDebugPage($data);
    }
    
    /**
     * Test API connectivity - public endpoint
     */
    public function testApi()
    {
        // This endpoint is publicly accessible for testing API connectivity
        return $this->response->setJSON([
            'success' => true,
            'message' => 'API connectivity test successful',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
            'environment' => ENVIRONMENT,
            'request_data' => [
                'method' => $this->request->getMethod(),
                'uri' => current_url(),
                'query' => $this->request->getGet(),
                'body' => $this->request->getJSON(true)
            ]
        ]);
    }
    
    /**
     * Test database connectivity and permissions
     */
    public function dbTest()
    {
        // Only accessible by superadmin for security reasons
        if (!$this->auth->hasRole('superadmin')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'This debug endpoint is only available to superadmins'
            ]);
        }
        
        // Define path to database from config
        $dbPath = WRITEPATH . 'db/cobranzas.db';

        try {
            // Check if database file exists
            if (!file_exists($dbPath)) {
                throw new \Exception("Database file not found at: {$dbPath}");
            }
            
            // Check file permissions
            $perms = substr(sprintf('%o', fileperms($dbPath)), -4);
            $isWritable = is_writable($dbPath);
            $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dbPath)) : null;
            $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($dbPath)) : null;
            
            // Try to open database connection directly
            $db = new \SQLite3($dbPath);
            
            // Try to create a temporary table
            $db->exec('CREATE TABLE IF NOT EXISTS db_test (id INTEGER PRIMARY KEY, test_value TEXT)');
            
            // Try to insert a value
            $timestamp = date('Y-m-d H:i:s');
            $db->exec("INSERT INTO db_test (test_value) VALUES ('Test write at {$timestamp}')");
            
            // Query to verify insertion
            $result = $db->query('SELECT * FROM db_test ORDER BY id DESC LIMIT 1');
            $lastRow = $result->fetchArray(SQLITE3_ASSOC);
            
            // Also try through CodeIgniter's Database service
            $dbService = \Config\Database::connect();
            $dbService->table('db_test')->insert(['test_value' => "CI Test at {$timestamp}"]);
            $ciResult = $dbService->table('db_test')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
            
            // Response data
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Database is writable!',
                'database' => [
                    'path' => $dbPath,
                    'exists' => true,
                    'writable' => $isWritable,
                    'permissions' => $perms,
                    'owner' => $owner ? ($owner['name'] ?? 'unknown') : 'posix_function_unavailable',
                    'group' => $group ? ($group['name'] ?? 'unknown') : 'posix_function_unavailable',
                    'size' => filesize($dbPath)
                ],
                'direct_test' => [
                    'success' => true,
                    'last_row' => $lastRow
                ],
                'ci_test' => [
                    'success' => true,
                    'last_row' => $ciResult
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'database' => [
                        'path' => $dbPath,
                        'exists' => file_exists($dbPath),
                        'writable' => isset($isWritable) ? $isWritable : (file_exists($dbPath) ? is_writable($dbPath) : false),
                        'permissions' => isset($perms) ? $perms : (file_exists($dbPath) ? substr(sprintf('%o', fileperms($dbPath)), -4) : 'unknown'),
                        'size' => file_exists($dbPath) ? filesize($dbPath) : 0
                    ]
                ]);
        }
    }
    
    /**
     * Helper method to render debug data as a simple HTML page
     */
    private function renderDebugPage($data)
    {
        $output = '<!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Debug Information</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <style>
                    pre {
                        background-color: #f5f5f5;
                        padding: 1rem;
                        border-radius: 0.25rem;
                        overflow-x: auto;
                    }
                </style>
            </head>
            <body>
                <div class="container mt-4">
                    <h1>Debug Information</h1>
                    <div class="row">
                        <div class="col">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Organization Context</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Current User:</strong> ' . $data['user']['name'] . ' (ID: ' . $data['user']['id'] . ', Role: ' . $data['user']['role'] . ')</p>
                                    <p><strong>User\'s Fixed Organization ID:</strong> ' . $data['user']['organization_id'] . '</p>
                                    <p><strong>Current Organization ID (with session selection):</strong> ' . ($data['current_organization_id'] ?: 'NONE') . '</p>
                                    <p><strong>Selected Organization ID in Session:</strong> ' . ($data['session']['selected_organization_id'] ?? 'NONE') . '</p>
                                </div>
                            </div>';
        
        // Only for CSRF debug page
        if (isset($data['token_name'])) {
            $output .= '
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">CSRF Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>CSRF Token Name:</strong> ' . $data['token_name'] . '</p>
                        <p><strong>Current CSRF Token Value:</strong> ' . $data['token_value'] . '</p>
                        <p><strong>Session CSRF Token:</strong> ' . $data['session_token'] . '</p>
                        <p><strong>Cookie CSRF Token:</strong> ' . $data['cookie_value'] . '</p>
                    </div>
                </div>';
        }
        
        // Only for organization debug page
        if (isset($data['organizations'])) {
            $output .= '
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Organizations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Switch</th>
                                        <th>Clients</th>
                                        <th>Invoices</th>
                                        <th>Portfolios</th>
                                        <th>Payments</th>
                                    </tr>
                                </thead>
                                <tbody>';
            
            foreach ($data['organizations'] as $org) {
                $isCurrent = $data['current_organization_id'] == $org['id'];
                $trClass = $isCurrent ? 'table-primary' : '';
                $output .= '
                    <tr class="' . $trClass . '">
                        <td>' . $org['id'] . '</td>
                        <td>' . $org['name'] . ($isCurrent ? ' (CURRENT)' : '') . '</td>
                        <td><a href="' . base_url('/debug/orgContext?org_id=' . $org['id']) . '" class="btn btn-sm btn-primary">Switch</a></td>
                        <td>' . ($data['stats']['clients']['by_org'][$org['id']] ?? 0) . '</td>
                        <td>' . ($data['stats']['invoices']['by_org'][$org['id']] ?? 0) . '</td>
                        <td>' . ($data['stats']['portfolios']['by_org'][$org['id']] ?? 0) . '</td>
                        <td>' . (isset($data['stats']['payments']['by_org'][$org['id']]) ? $data['stats']['payments']['by_org'][$org['id']] : 'N/A') . '</td>
                    </tr>';
            }
            
            $output .= '
                    <tr class="table-secondary">
                        <td colspan="3"><strong>TOTALS</strong></td>
                        <td><strong>' . $data['stats']['clients']['total'] . '</strong></td>
                        <td><strong>' . $data['stats']['invoices']['total'] . '</strong></td>
                        <td><strong>' . $data['stats']['portfolios']['total'] . '</strong></td>
                        <td><strong>' . (isset($data['stats']['payments']['total']) ? $data['stats']['payments']['total'] : 'N/A') . '</strong></td>
                    </tr>
                </tbody>
                </table>
                </div>
                <a href="' . base_url('/debug/orgContext?clear_org=1') . '" class="btn btn-warning">Clear Organization Selection</a>
                </div>
                </div>';
        }
        
        $output .= '
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Session Data</h5>
                    </div>
                    <div class="card-body">
                        <pre>' . json_encode($data['session'], JSON_PRETTY_PRINT) . '</pre>
                    </div>
                </div>
                </div>
                </div>
                
                <div class="container mb-4">
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Test Links</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <a href="' . site_url('/portfolios') . '" class="list-group-item list-group-item-action">Portfolios</a>
                                        <a href="' . site_url('/clients') . '" class="list-group-item list-group-item-action">Clients</a>
                                        <a href="' . site_url('/invoices') . '" class="list-group-item list-group-item-action">Invoices</a>
                                        <a href="' . site_url('/payments') . '" class="list-group-item list-group-item-action">Payments</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            </body>
            </html>';
        
        return $this->response->setBody($output);
    }
}