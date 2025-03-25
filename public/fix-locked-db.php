<?php
// Script para intentar solucionar una base de datos SQLite bloqueada
header('Content-Type: text/plain');

// Define path to database
$dbPath = dirname(__DIR__) . '/writable/db/cobranzas.db';
$journalPath = $dbPath . '-journal';
$walPath = $dbPath . '-wal';
$shmPath = $dbPath . '-shm';

echo "=== SQLITE DATABASE FIXER ===\n";
echo "Executed at: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "DB Path: {$dbPath}\n\n";

// Paso 1: Verificar permisos actuales
echo "Step 1: Checking current permissions\n";
echo "- Database exists: " . (file_exists($dbPath) ? "Yes" : "No") . "\n";
if (file_exists($dbPath)) {
    echo "- Database permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";
    echo "- Database size: " . filesize($dbPath) . " bytes\n";
}

// Paso 2: Verificar y eliminar archivos de journal si existen
echo "\nStep 2: Checking journal files\n";
$hasJournal = file_exists($journalPath);
$hasWal = file_exists($walPath);
$hasShm = file_exists($shmPath);

echo "- Journal file exists: " . ($hasJournal ? "Yes" : "No") . "\n";
echo "- WAL file exists: " . ($hasWal ? "Yes" : "No") . "\n";
echo "- SHM file exists: " . ($hasShm ? "Yes" : "No") . "\n";

// Paso 3: Intentar hacer una copia de seguridad de la base de datos
echo "\nStep 3: Creating backup\n";
$backupPath = $dbPath . '.bak.' . time();
if (file_exists($dbPath)) {
    $backupResult = copy($dbPath, $backupPath);
    echo "- Backup result: " . ($backupResult ? "Success - {$backupPath}" : "Failed") . "\n";
} else {
    echo "- Backup skipped: Database file doesn't exist\n";
}

// Paso 4: Intentar eliminar archivos de journal
echo "\nStep 4: Removing journal files\n";
if ($hasJournal) {
    $deleteResult = @unlink($journalPath);
    echo "- Journal deletion: " . ($deleteResult ? "Success" : "Failed") . "\n";
}
if ($hasWal) {
    $deleteResult = @unlink($walPath);
    echo "- WAL deletion: " . ($deleteResult ? "Success" : "Failed") . "\n";
}
if ($hasShm) {
    $deleteResult = @unlink($shmPath);
    echo "- SHM deletion: " . ($deleteResult ? "Success" : "Failed") . "\n";
}

// Paso 5: Ajustar permisos
echo "\nStep 5: Setting permissions\n";
if (file_exists($dbPath)) {
    $chmodResult = @chmod($dbPath, 0666);
    echo "- chmod 666: " . ($chmodResult ? "Success" : "Failed") . "\n";
    
    // Si tenemos acceso a las funciones de cambio de propietario
    if (function_exists('chown')) {
        $chownResult = @chown($dbPath, 'www-data');
        echo "- chown www-data: " . ($chownResult ? "Success" : "Failed") . "\n";
        
        $chgrpResult = @chgrp($dbPath, 'www-data');
        echo "- chgrp www-data: " . ($chgrpResult ? "Success" : "Failed") . "\n";
    } else {
        echo "- chown/chgrp: Not available in this PHP environment\n";
    }
}

// Paso 6: Intentar abrir la base de datos en modo de solo lectura
echo "\nStep 6: Testing read-only connection\n";
try {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    echo "- Read-only connection: Success\n";
    
    $result = $db->query("SELECT COUNT(*) as count FROM sqlite_master");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "- Number of tables: {$row['count']}\n";
    
    $db->close();
} catch (Exception $e) {
    echo "- Read-only test failed: " . $e->getMessage() . "\n";
}

// Paso 7: Intentar una recuperación VACUUM si es posible
echo "\nStep 7: Attempting VACUUM (if writable)\n";
try {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
    echo "- Writable connection: Success\n";
    
    $vacuumResult = $db->exec("VACUUM");
    echo "- VACUUM result: " . ($vacuumResult ? "Success" : "Failed") . "\n";
    
    $db->close();
} catch (Exception $e) {
    echo "- VACUUM failed: " . $e->getMessage() . "\n";
}

echo "\nFix process completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Next steps: Try accessing the database again. If issues persist, manually restore from backup or check server logs.\n";