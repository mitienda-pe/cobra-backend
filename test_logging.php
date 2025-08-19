<?php

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

$pathsPath = FCPATH . 'app/Config/Paths.php';
require realpath($pathsPath) ?: $pathsPath;

$paths = new Config\Paths();

$bootstrap = FCPATH . 'vendor/codeigniter4/framework/system/bootstrap.php';
require realpath($bootstrap) ?: $bootstrap;

$app = Config\Services::codeigniter();
$app->initialize();

// Test CodeIgniter logging
log_message('error', '🧪 CODEIGNITER LOGGING TEST - ' . date('Y-m-d H:i:s'));
echo "CodeIgniter logging test completed. Check writable/logs/log-" . date('Y-m-d') . ".log\n";

// Test that we can create controllers
echo "Testing if we can instantiate SuperadminLigoConfigController...\n";
try {
    $controller = new \App\Controllers\SuperadminLigoConfigController();
    echo "✅ SuperadminLigoConfigController instantiated successfully\n";
} catch (\Exception $e) {
    echo "❌ Error instantiating SuperadminLigoConfigController: " . $e->getMessage() . "\n";
}

// Test database connection
echo "Testing database connection...\n";
try {
    $db = \Config\Database::connect();
    $query = $db->query("SELECT 1 as test");
    $result = $query->getRowArray();
    if ($result['test'] == 1) {
        echo "✅ Database connection successful\n";
    }
} catch (\Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
}