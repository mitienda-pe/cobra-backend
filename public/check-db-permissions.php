<?php
// Script para verificar permisos de la base de datos sin intentar abrirla
header('Content-Type: text/plain');

// Define path to database
$dbPath = dirname(__DIR__) . '/writable/db/cobranzas.db';

echo "=== DATABASE PERMISSIONS CHECK ===\n";
echo "Executed at: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "DB Path: {$dbPath}\n\n";

// Verificar el archivo de la base de datos
echo "Database file check:\n";
echo "- Exists: " . (file_exists($dbPath) ? "Yes" : "No") . "\n";
echo "- Readable: " . (is_readable($dbPath) ? "Yes" : "No") . "\n";
echo "- Writable: " . (is_writable($dbPath) ? "Yes" : "No") . "\n";
echo "- Size: " . (file_exists($dbPath) ? filesize($dbPath) . " bytes" : "N/A") . "\n";
echo "- Permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";

// Mostrar información sobre el usuario que ejecuta PHP
echo "\nPHP User info:\n";
echo "- Current PHP user: " . get_current_user() . "\n";
echo "- PHP process ID: " . getmypid() . "\n";

// Verificar el directorio de la base de datos
$dbDir = dirname($dbPath);
echo "\nDatabase directory check:\n";
echo "- Path: {$dbDir}\n";
echo "- Exists: " . (file_exists($dbDir) ? "Yes" : "No") . "\n";
echo "- Readable: " . (is_readable($dbDir) ? "Yes" : "No") . "\n";
echo "- Writable: " . (is_writable($dbDir) ? "Yes" : "No") . "\n";
echo "- Permissions: " . substr(sprintf('%o', fileperms($dbDir)), -4) . "\n";

// Intentar crear un archivo temporal para verificar permisos de escritura
$testFile = $dbDir . "/test_" . time() . ".txt";
echo "\nWriting test:\n";
$writeTest = false;
try {
    $writeTest = file_put_contents($testFile, "Test at " . date('Y-m-d H:i:s'));
    echo "- Write test: " . ($writeTest !== false ? "Successful ({$writeTest} bytes)" : "Failed") . "\n";
    
    if ($writeTest !== false) {
        echo "- Test file created: {$testFile}\n";
        echo "- Test file content read-back: " . file_get_contents($testFile) . "\n";
        unlink($testFile);
        echo "- Test file deleted: Yes\n";
    }
} catch (Exception $e) {
    echo "- Write test error: " . $e->getMessage() . "\n";
}

// Verificar procesos que puedan estar usando la base de datos
echo "\nProcesses using the database (if command is available):\n";
$output = [];
exec("lsof {$dbPath} 2>&1", $output, $result);
if ($result === 0 && !empty($output)) {
    echo implode("\n", $output) . "\n";
} else {
    echo "Could not check processes or no processes are using the database.\n";
}

echo "\nSystem info:\n";
echo "- Server software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "- Server name: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "\n";
echo "- Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "- Script filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . "\n";

// Intentar leer el journal de SQLite (si existe)
$journalFile = $dbPath . "-journal";
echo "\nSQLite journal file check:\n";
echo "- Path: {$journalFile}\n";
echo "- Exists: " . (file_exists($journalFile) ? "Yes" : "No") . "\n";
if (file_exists($journalFile)) {
    echo "- Size: " . filesize($journalFile) . " bytes\n";
    echo "- Permissions: " . substr(sprintf('%o', fileperms($journalFile)), -4) . "\n";
}

// Mostrar valores de configuración de SQLite
echo "\nSQLite configuration (if extension is loaded):\n";
if (extension_loaded('sqlite3')) {
    echo "- SQLite3 version: " . SQLite3::version()['versionString'] . "\n";
    $constants = get_defined_constants(true);
    if (isset($constants['sqlite3'])) {
        foreach ($constants['sqlite3'] as $name => $value) {
            if (strpos($name, 'SQLITE3_') === 0) {
                echo "- {$name}: {$value}\n";
            }
        }
    }
} else {
    echo "SQLite3 extension is not loaded.\n";
}

echo "\nCheck completed at: " . date('Y-m-d H:i:s') . "\n";