<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestLigoConnectivity extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:ligo-connectivity';
    protected $description = 'Test connectivity to various Ligo API URLs';

    public function run(array $params)
    {
        CLI::write('=== Testing Ligo API Connectivity ===', 'yellow');
        CLI::write('');

        $urlsToTest = [
            // Original URLs from ligocloud.tech
            'https://cce-api-gateway-prod.ligocloud.tech',
            'https://cce-auth-prod.ligocloud.tech',
            'https://cce-api-gateway-dev.ligocloud.tech',
            'https://cce-auth-dev.ligocloud.tech',
            
            // New URLs from ligo.pe
            'https://api.ligo.pe',
            'https://auth.ligo.pe',
            'https://dev-api.ligo.pe',
            'https://dev-auth.ligo.pe',
            
            // Alternative URLs
            'https://ligocloud.tech',
            'https://ligo.pe',
        ];

        foreach ($urlsToTest as $url) {
            CLI::write("Testing: {$url}", 'cyan');
            
            $result = $this->testUrl($url);
            
            if ($result['success']) {
                CLI::write("  ✓ Reachable - HTTP {$result['http_code']}", 'green');
                if (!empty($result['redirect'])) {
                    CLI::write("  → Redirects to: {$result['redirect']}", 'yellow');
                }
            } else {
                CLI::write("  ✗ Failed: {$result['error']}", 'red');
            }
            CLI::write('');
        }
    }

    private function testUrl($url)
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // Don't follow redirects automatically
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'CobraPepe-Test/1.0'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $redirectUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        
        curl_close($curl);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => true,
            'http_code' => $httpCode,
            'redirect' => $redirectUrl
        ];
    }
}