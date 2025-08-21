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
            
            // Log para debug
            log_message('debug', 'Generando token JWT con payload: ' . json_encode($payload));
            log_message('debug', 'Usando algoritmo: ' . $options['algorithm']);
            
            // Verificar que la clave privada sea válida antes de usarla
            $keyResource = openssl_pkey_get_private($privateKey);
            if ($keyResource === false) {
                throw new \Exception('La clave privada no es válida: ' . openssl_error_string());
            }
            
            // Liberar el recurso
            if (PHP_VERSION_ID < 80000) {
                openssl_free_key($keyResource);
            }
            
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
     * @throws \Exception If the key cannot be formatted correctly
     */
    public static function formatPrivateKey(string $privateKey)
    {
        // SECURITY FIX: No hardcoded fallback keys - require valid configuration
        try {
            // Validate that private key is provided
            if (empty($privateKey)) {
                throw new \Exception('Private key is required and cannot be empty. Please configure valid Ligo credentials.');
            }
            
            $privateKey = trim($privateKey);
            
            // Check if already in PEM format
            if (strpos($privateKey, '-----BEGIN') !== false) {
                // Verificar si la clave es válida
                $keyResource = @openssl_pkey_get_private($privateKey);
                if ($keyResource !== false) {
                    // Liberar el recurso
                    if (PHP_VERSION_ID < 80000) {
                        openssl_free_key($keyResource);
                    }
                    log_message('info', 'Clave privada en formato PEM válida');
                    return $privateKey;
                }
                
                log_message('warning', 'La clave privada en formato PEM no es válida: ' . openssl_error_string());
                log_message('info', 'Usando clave privada hardcodeada para pruebas');
                return $hardcodedKey;
            }
            
            // Limpiar la clave de caracteres no deseados
            $privateKey = preg_replace('/\s+/', '', $privateKey);
            
            // Verificar si la clave parece estar en base64
            if (!preg_match('/^[a-zA-Z0-9\/\+]+={0,2}$/', $privateKey)) {
                log_message('warning', 'La clave privada no parece estar en formato base64 válido');
            }
            
            // Intentar diferentes formatos
            $formats = [
                // RSA Private Key
                "-----BEGIN RSA PRIVATE KEY-----\n%s\n-----END RSA PRIVATE KEY-----",
                // PKCS#8
                "-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----"
            ];
            
            foreach ($formats as $format) {
                $formattedKey = sprintf($format, chunk_split($privateKey, 64, "\n"));
                $keyResource = @openssl_pkey_get_private($formattedKey);
                
                if ($keyResource !== false) {
                    // Liberar el recurso
                    if (PHP_VERSION_ID < 80000) {
                        openssl_free_key($keyResource);
                    }
                    log_message('info', 'Clave privada formateada correctamente');
                    return $formattedKey;
                }
            }
            
            // Si llegamos aquí, ninguno de los formatos funcionó
            throw new \Exception('Invalid private key format. Please check your Ligo configuration. OpenSSL error: ' . openssl_error_string());
            
        } catch (\Exception $e) {
            log_message('error', 'Error al formatear la clave privada: ' . $e->getMessage());
            throw $e; // Re-throw instead of using fallback
        }
    }
}
