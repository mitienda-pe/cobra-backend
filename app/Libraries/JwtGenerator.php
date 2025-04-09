<?php

namespace App\Libraries;

/**
 * JWT Generator for Ligo API
 * 
 * This class generates JWT tokens for authentication with Ligo API
 */
class JwtGenerator
{
    /**
     * Generate JWT token using RS256 algorithm
     * 
     * @param array $payload Payload data
     * @param string $privateKey RSA private key in PEM format
     * @param array $options Additional options (algorithm, expiry, etc.)
     * @return string Generated JWT token
     * @throws \Exception If token generation fails
     */
    public static function generateToken(array $payload, string $privateKey, array $options = [])
    {
        try {
            // Ensure Firebase JWT library is loaded
            require_once APPPATH . '../vendor/autoload.php';
            
            // Default options
            $defaultOptions = [
                'algorithm' => 'RS256',
                'expiresIn' => 3600, // 1 hour in seconds
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com'
            ];
            
            // Merge options
            $options = array_merge($defaultOptions, $options);
            
            // Prepare payload with standard claims
            $now = time();
            $payload = array_merge($payload, [
                'iat' => $now,
                'exp' => $now + $options['expiresIn'],
                'iss' => $options['issuer'],
                'aud' => $options['audience'],
                'sub' => $options['subject']
            ]);
            
            // Generate token
            $token = \Firebase\JWT\JWT::encode($payload, $privateKey, $options['algorithm']);
            
            return $token;
        } catch (\Exception $e) {
            log_message('error', 'Error generando JWT: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Format a private key string to proper PEM format if needed
     * 
     * @param string $privateKey Private key string
     * @return string Formatted private key
     */
    public static function formatPrivateKey(string $privateKey)
    {
        $privateKey = trim($privateKey);
        
        // Check if already in PEM format
        if (strpos($privateKey, '-----BEGIN') !== false) {
            return $privateKey;
        }
        
        // Try to format as RSA private key
        return "-----BEGIN RSA PRIVATE KEY-----\n" . 
               chunk_split($privateKey, 64, "\n") . 
               "-----END RSA PRIVATE KEY-----";
    }
}
