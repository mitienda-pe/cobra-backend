<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class LigoDebugController extends BaseController
{
    protected $organizationModel;
    
    public function __construct()
    {
        $this->organizationModel = new \App\Models\OrganizationModel();
    }
    
    /**
     * Debug Ligo integration using UUID
     * 
     * @return mixed
     */
    public function status()
    {
        // Only allow superadmin to access this page
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        $organizationUuid = $this->request->getGet('uuid');
        
        if (!$organizationUuid) {
            return $this->response->setJSON([
                'error' => 'Debe proporcionar el UUID de la organización'
            ]);
        }
        
        $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
        
        if (!$organization) {
            return $this->response->setJSON([
                'error' => 'Organización no encontrada'
            ]);
        }
        
        // Verificar estado de Ligo
        $ligoEnabled = isset($organization['ligo_enabled']) && $organization['ligo_enabled'];
        $hasValidCredentials = !empty($organization['ligo_username']) && 
                              !empty($organization['ligo_password']) && 
                              !empty($organization['ligo_company_id']);
        $hasValidToken = !empty($organization['ligo_token']) && 
                         !empty($organization['ligo_token_expiry']) && 
                         strtotime($organization['ligo_token_expiry']) > time();
        
        // Intentar obtener un token directamente con cURL
        $authResult = $this->testLigoAuth($organization);
        
        // Datos de diagnóstico
        $diagnosticData = [
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'organization_name' => $organization['name'],
            'ligo_enabled' => $ligoEnabled ? 'Sí' : 'No',
            'has_valid_credentials' => $hasValidCredentials ? 'Sí' : 'No',
            'has_valid_token' => $hasValidToken ? 'Sí' : 'No',
            'token_expiry' => $organization['ligo_token_expiry'] ?? 'No disponible',
            'token_expiry_timestamp' => $organization['ligo_token_expiry'] ? strtotime($organization['ligo_token_expiry']) : 'N/A',
            'current_timestamp' => time(),
            'token_is_valid' => $hasValidToken ? 'Sí' : 'No',
            'auth_result' => isset($authResult->error) ? 'Error: ' . $authResult->error : 'Éxito',
            'ligo_username' => $organization['ligo_username'] ? 'Configurado' : 'No configurado',
            'ligo_password' => $organization['ligo_password'] ? 'Configurado' : 'No configurado',
            'ligo_company_id' => $organization['ligo_company_id'] ?? 'No configurado',
            'ligo_auth_error' => $organization['ligo_auth_error'] ?? 'Ninguno'
        ];
        
        // Si hay un token válido
        if (!isset($authResult->error)) {
            $diagnosticData['token_obtained'] = 'Sí';
            $diagnosticData['token_value'] = substr($authResult->token, 0, 20) . '...';
        }
        
        // Actualizar la organización con el nuevo token si se obtuvo correctamente
        if (!isset($authResult->error) && isset($authResult->token)) {
            $this->organizationModel->update($organization['id'], [
                'ligo_token' => $authResult->token,
                'ligo_token_expiry' => date('Y-m-d H:i:s', time() + 3600), // 1 hora
                'ligo_auth_error' => null,
                'ligo_enabled' => 1 // Habilitar Ligo automáticamente si la autenticación es exitosa
            ]);
            
            $diagnosticData['token_updated'] = 'Sí';
            $diagnosticData['ligo_enabled_updated'] = 'Sí';
            $diagnosticData['message'] = 'Se ha actualizado el token y habilitado Ligo para esta organización. Por favor, intente generar un QR nuevamente.';
        }
        
        return $this->response->setJSON($diagnosticData);
    }
    
    /**
     * Force enable Ligo for an organization using UUID
     * 
     * @return mixed
     */
    public function enable()
    {
        // Only allow superadmin to access this page
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        $organizationUuid = $this->request->getGet('uuid');
        
        if (!$organizationUuid) {
            return $this->response->setJSON([
                'error' => 'Debe proporcionar el UUID de la organización'
            ]);
        }
        
        $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
        
        if (!$organization) {
            return $this->response->setJSON([
                'error' => 'Organización no encontrada'
            ]);
        }
        
        // Forzar la habilitación de Ligo
        $this->organizationModel->update($organization['id'], [
            'ligo_enabled' => 1
        ]);
        
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Ligo habilitado para la organización ' . $organization['name']
        ]);
    }
    
    /**
     * Test Ligo API authentication with provided credentials
     *
     * @param array $organization Organization with Ligo credentials
     * @return object Result with token or error
     */
    private function testLigoAuth($organization)
    {
        log_message('debug', 'Probando autenticación con Ligo para organización: ' . $organization['id']);
        
        try {
            // Verificar si hay credenciales
            if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
                return (object)['error' => 'Credenciales de Ligo incompletas'];
            }
            
            // Eliminar espacios en blanco de las credenciales
            $username = trim($organization['ligo_username']);
            $password = trim($organization['ligo_password']);
            $companyId = trim($organization['ligo_company_id']);
            
            $curl = curl_init();
            
            // Datos de autenticación
            $authData = [
                'username' => $username,
                'password' => $password
            ];
            
            // URL de autenticación
            $prefix = 'dev'; // Cambiar a 'prod' para entorno de producción
            $url = 'https://cce-auth-' . $prefix . '.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
            
            log_message('debug', 'URL de autenticación Ligo: ' . $url);
            
            // Token de autorización requerido por Ligo
            $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxMzkwNDEsImV4cCI6MTc0NDE0MjY0MSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.chWrhOkQXo2Yc9mOhB8kIHbSmQECtA_PxTsSCcOTCC6OJs7IkDAyj3vkISW7Sm6G88R3KXgxSWhPT4QmShw3xV9a4Jl0FTBQy2KRdTCzbTgRifs9GN0X5KR7KhfChnDSKNosnVQD9QrqTCdlqpvW75vO1rWfTRSXpMtKZRUvy6fPyESv2QxERlo-441e2EwwCly1kgLftpTcMa0qCr-OplD4Iv_YaOw-J5IPAdYqkVPqHQQZO2LCLjP-Q51KPW04VtTyf7UbO6g4OvUb6a423XauAhUFtSw0oGZS11hAYOPSIKO0w6JERLOvJr48lKaouogf0g_M18nZeSDPMZwCWw';
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($authData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authorizationToken
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($err) {
                log_message('error', 'Error al conectar con Ligo: ' . $err);
                return (object)['error' => 'Error de conexión: ' . $err];
            }
            
            // Log de respuesta
            log_message('debug', 'Código de respuesta HTTP: ' . $info['http_code']);
            
            // Verificar si la respuesta es HTML
            if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
                log_message('error', 'Ligo Auth API devolvió HTML en lugar de JSON');
                return (object)['error' => 'Respuesta inesperada del servidor'];
            }
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta JSON: ' . json_last_error_msg());
                return (object)['error' => 'Respuesta inválida: ' . json_last_error_msg()];
            }
            
            // Verificar si hay token en la respuesta
            if (!isset($decoded->data) || !isset($decoded->data->access_token)) {
                log_message('error', 'No se recibió token en la respuesta: ' . json_encode($decoded));
                
                // Extraer mensaje de error
                $errorMsg = 'No token in auth response';
                if (isset($decoded->message)) {
                    $errorMsg .= ': ' . $decoded->message;
                } elseif (isset($decoded->errors)) {
                    $errorMsg .= ': ' . (is_string($decoded->errors) ? $decoded->errors : json_encode($decoded->errors));
                } elseif (isset($decoded->error)) {
                    $errorMsg .= ': ' . (is_string($decoded->error) ? $decoded->error : json_encode($decoded->error));
                }
                
                return (object)['error' => $errorMsg];
            }
            
            log_message('info', 'Autenticación con Ligo exitosa, token obtenido');
            
            return (object)[
                'token' => $decoded->data->access_token,
                'userId' => $decoded->data->userId ?? null,
                'companyId' => $decoded->data->companyId ?? $companyId
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Excepción en autenticación Ligo: ' . $e->getMessage());
            return (object)['error' => 'Error interno: ' . $e->getMessage()];
        }
    }
}
