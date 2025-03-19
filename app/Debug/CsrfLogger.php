<?php

namespace App\Debug;

class CsrfLogger
{
    public static function log($message, $data = [])
    {
        $logFile = WRITEPATH . 'logs/csrf_debug.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $dataStr = !empty($data) ? json_encode($data) : '';
        
        $logMessage = "[{$timestamp}] {$message} {$dataStr}" . PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}