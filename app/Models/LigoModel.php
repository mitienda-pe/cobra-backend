<?php

namespace App\Models;

use CodeIgniter\Model;

class LigoModel extends Model
{
    protected $organizationModel;
    protected $superadminLigoConfigModel;
    protected $ligoBaseUrl;
    protected $ligoAuthUrl;

    public function __construct()
    {
        parent::__construct();
        $this->organizationModel = new OrganizationModel();
        $this->superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // URLs base de Ligo según el entorno
        $environment = env('CI_ENVIRONMENT', 'development');
        if ($environment === 'production') {
            $this->ligoBaseUrl = env('LIGO_PROD_URL', 'https://api.ligo.pe');
            $this->ligoAuthUrl = env('LIGO_PROD_AUTH_URL', 'https://auth.ligo.pe');
        } else {
            $this->ligoBaseUrl = env('LIGO_DEV_URL', 'https://dev-api.ligo.pe');
            $this->ligoAuthUrl = env('LIGO_DEV_AUTH_URL', 'https://dev-auth.ligo.pe');
        }
    }

    protected function getOrganizationFromSession()
    {
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            log_message('debug', 'LigoModel: No organization ID in session');
            return null;
        }
        
        $organization = $this->organizationModel->find($organizationId);
        
        if (!$organization) {
            log_message('debug', 'LigoModel: Organization not found with ID: ' . $organizationId);
            return null;
        }
        
        log_message('debug', 'LigoModel: Using organization: ' . $organization['name'] . ' (ID: ' . $organizationId . ')');
        return $organization;
    }

    /**
     * Get centralized Ligo configuration from superadmin
     */
    protected function getSuperadminLigoConfig()
    {
        log_message('info', 'LigoModel: Getting superadmin Ligo configuration...');
        
        // First try to get any active configuration regardless of environment
        $config = $this->superadminLigoConfigModel->where('enabled', 1)
                                                  ->where('is_active', 1)
                                                  ->first();
        
        log_message('debug', 'LigoModel: Active config query result: ' . ($config ? 'Found ID ' . $config['id'] : 'Not found'));
        
        if (!$config) {
            // Fallback: try to determine environment from CI_ENVIRONMENT
            $environment = env('CI_ENVIRONMENT', 'development') === 'production' ? 'prod' : 'dev';
            log_message('debug', 'LigoModel: Fallback to environment-specific config: ' . $environment);
            
            $config = $this->superadminLigoConfigModel->getActiveConfig($environment);
            
            if (!$config) {
                log_message('error', 'LigoModel: No active superadmin Ligo configuration found for environment: ' . $environment);
                
                // Debug: List all available configs
                $allConfigs = $this->superadminLigoConfigModel->findAll();
                log_message('debug', 'LigoModel: Available configurations: ' . json_encode(array_map(function($c) {
                    return [
                        'id' => $c['id'],
                        'environment' => $c['environment'],
                        'enabled' => $c['enabled'],
                        'is_active' => $c['is_active'],
                        'has_username' => !empty($c['username']),
                        'has_company_id' => !empty($c['company_id'])
                    ];
                }, $allConfigs)));
                
                return null;
            }
        }

        log_message('info', 'LigoModel: Found config ID ' . $config['id'] . ' for environment: ' . $config['environment']);
        
        // Log config details (without sensitive data)
        log_message('debug', 'LigoModel: Config details: ' . json_encode([
            'id' => $config['id'],
            'environment' => $config['environment'],
            'enabled' => $config['enabled'],
            'is_active' => $config['is_active'],
            'has_username' => !empty($config['username']),
            'has_password' => !empty($config['password']),
            'has_company_id' => !empty($config['company_id']),
            'has_account_id' => !empty($config['account_id']),
            'has_private_key' => !empty($config['private_key']),
            'has_debtor_name' => !empty($config['debtor_name']),
            'has_debtor_id' => !empty($config['debtor_id'])
        ]));

        $isComplete = $this->superadminLigoConfigModel->isConfigurationComplete($config);
        log_message('info', 'LigoModel: Configuration completeness check: ' . ($isComplete ? 'COMPLETE' : 'INCOMPLETE'));
        
        if (!$isComplete) {
            log_message('error', 'LigoModel: Superadmin Ligo configuration is incomplete for environment: ' . $config['environment']);
            
            // Log missing fields
            $requiredFields = ['username', 'password', 'company_id', 'private_key'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($config[$field])) {
                    $missingFields[] = $field;
                }
            }
            log_message('error', 'LigoModel: Missing required fields: ' . implode(', ', $missingFields));
            
            return null;
        }

        log_message('info', 'LigoModel: Using centralized Ligo config ID ' . $config['id'] . ' for environment: ' . $config['environment']);
        return $config;
    }

    protected function makeApiRequest($endpoint, $method = 'GET', $data = null, $requiresAuth = true)
    {
        log_message('info', "LigoModel: Making API request to {$endpoint} (method: {$method})");
        
        // Get centralized Ligo configuration
        $ligoConfig = $this->getSuperadminLigoConfig();
        
        if (!$ligoConfig) {
            log_message('error', 'LigoModel: No centralized Ligo configuration available for API request');
            return ['error' => 'Configuración de Ligo no disponible. Contacte al administrador.'];
        }

        // We still need organization for context (but not for credentials)
        $organization = $this->getOrganizationFromSession();
        if (!$organization) {
            log_message('error', 'LigoModel: No organization available for API request context');
            return ['error' => 'No hay organización seleccionada'];
        }

        $useEnv = $ligoConfig['environment'];
        
        log_message('debug', 'LigoModel: Using centralized Ligo config for environment: ' . $useEnv . ' with organization context: ' . $organization['name']);
        
        // Get API URLs from configuration
        $urls = $this->superadminLigoConfigModel->getApiUrls($useEnv);
        $this->ligoBaseUrl = $urls['api_url'];
        $this->ligoAuthUrl = $urls['auth_url'];

        $curl = curl_init();
        $url = $this->ligoBaseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($requiresAuth) {
            $token = $this->getAuthToken($ligoConfig);
            if (isset($token['error'])) {
                return $token;
            }
            $headers[] = 'Authorization: Bearer ' . $token['token'];
        }

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err . ' for URL: ' . $url);
            return ['error' => 'Error de conexión con Ligo API: ' . $err];
        }

        log_message('debug', 'Ligo API Response - HTTP Code: ' . $httpCode . ', Raw Response: ' . $response);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['message'] ?? 'Error en la API de Ligo';
            log_message('error', 'Ligo API HTTP Error ' . $httpCode . ': ' . $response . ' for URL: ' . $url);
            return ['error' => $errorMessage, 'http_code' => $httpCode, 'raw_response' => $response];
        }

        return $decodedResponse;
    }

    protected function getAuthToken($ligoConfig)
    {
        // Use centralized credentials
        $environment = $ligoConfig['environment'];
        log_message('debug', 'LigoModel: Auth using centralized config for environment: ' . $environment);
        
        // Ensure password is decrypted
        $password = $ligoConfig['password'];
        if (strpos($password, 'ENC:') === 0) {
            $password = base64_decode(substr($password, 4));
        }
        
        $authData = [
            'username' => $ligoConfig['username'],
            'password' => $password
        ];
        $companyId = $ligoConfig['company_id'];
        $privateKey = $ligoConfig['private_key'];

        log_message('debug', 'LigoModel: Auth data - username: ' . ($authData['username'] ?? 'null') . ', company_id: ' . ($companyId ?? 'null'));
        log_message('debug', 'LigoModel: Password length: ' . (isset($authData['password']) ? strlen($authData['password']) : 0));
        log_message('debug', 'LigoModel: Private key length: ' . (isset($privateKey) ? strlen($privateKey) : 0));

        // Validar que las credenciales estén presentes
        if (empty($authData['username']) || empty($authData['password']) || empty($companyId)) {
            log_message('error', 'LigoModel: Missing auth credentials in centralized config for environment: ' . $environment);
            log_message('error', 'LigoModel: Missing credentials details: username=' . ($authData['username'] ?? 'empty') . ', password=' . (empty($authData['password']) ? 'empty' : 'present') . ', company_id=' . ($companyId ?? 'empty'));
            return ['error' => "Missing authentication credentials in centralized configuration for {$environment} environment"];
        }

        // Verificar que la clave privada exista
        if (empty($privateKey)) {
            log_message('error', 'LigoModel: Private key not configured in centralized config for environment: ' . $environment);
            return ['error' => "Private key not configured in centralized configuration for {$environment} environment"];
        }

        // Get auth URL from centralized config
        $urls = $this->superadminLigoConfigModel->getApiUrls($environment);
        $authBaseUrl = $urls['auth_url'];
        
        $authUrl = $authBaseUrl . '/v1/auth/sign-in?companyId=' . $companyId;
        log_message('debug', 'LigoModel: Authenticating with centralized config - URL: ' . $authUrl . ' and username: ' . $authData['username'] . ' for env: ' . $environment);
        log_message('debug', 'LigoModel: API URLs - auth: ' . $authBaseUrl . ', api: ' . $urls['api_url']);

        // Generar token JWT usando la clave privada centralizada
        try {
            log_message('debug', 'LigoModel: Starting JWT token generation...');
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);
            log_message('debug', 'LigoModel: Private key formatted successfully');

            // Preparar payload
            $payload = [
                'companyId' => $companyId
            ];
            log_message('debug', 'LigoModel: JWT payload: ' . json_encode($payload));

            // Generar token JWT
            $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600 // 1 hora
            ]);

            log_message('info', 'LigoModel: JWT token generated successfully using centralized config');
            log_message('debug', 'LigoModel: JWT token: ' . substr($authorizationToken, 0, 30) . '...');
        } catch (\Exception $e) {
            log_message('error', 'LigoModel: Error generating JWT token with centralized config: ' . $e->getMessage());
            return ['error' => 'Error generating JWT token: ' . $e->getMessage()];
        }

        $curl = curl_init();
        $requestBody = json_encode($authData);
        
        log_message('debug', 'LigoModel: Preparing HTTP request to: ' . $authUrl);
        log_message('debug', 'LigoModel: Request body: ' . $requestBody);
        log_message('debug', 'LigoModel: Authorization header with JWT token length: ' . strlen($authorizationToken));

        $headers = [
            'Authorization: Bearer ' . $authorizationToken,  // JWT token como LigoQRController
            'Content-Type: application/json',
            'Content-Length: ' . strlen($requestBody),
            'Accept: application/json'
        ];
        
        log_message('debug', 'LigoModel: Request headers: ' . json_encode(array_map(function($h) { 
            return strpos($h, 'Authorization:') === 0 ? 'Authorization: Bearer [HIDDEN]' : $h; 
        }, $headers)));

        curl_setopt_array($curl, [
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        log_message('info', 'LigoModel: Executing authentication request...');
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        log_message('debug', 'LigoModel: cURL completed - HTTP Code: ' . $httpCode . ', Error: ' . ($err ?: 'none'));

        curl_close($curl);

        if ($err) {
            log_message('error', 'Ligo Auth Error: ' . $err . ' for URL: ' . $authUrl);
            return ['error' => 'Error de conexión con Ligo Auth: ' . $err];
        }

        log_message('debug', 'Ligo Auth Response - HTTP Code: ' . $httpCode . ', Raw Response: ' . $response);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['message'] ?? 'Error de autenticación con Ligo';
            log_message('error', 'Ligo Auth HTTP Error ' . $httpCode . ': ' . $response . ' for URL: ' . $authUrl);
            return ['error' => $errorMessage, 'http_code' => $httpCode, 'raw_response' => $response];
        }

        // Verificar si hay token en la respuesta (adaptado del formato de LigoQRController)
        if (!isset($decodedResponse['data']['access_token']) && !isset($decodedResponse['data']['token'])) {
            log_message('error', 'Ligo Auth: No token in response: ' . $response);

            // Extraer mensaje de error
            $errorMsg = 'No token in auth response';
            if (isset($decodedResponse['message'])) {
                $errorMsg .= ': ' . $decodedResponse['message'];
            } elseif (isset($decodedResponse['errors'])) {
                $errorMsg .= ': ' . (is_string($decodedResponse['errors']) ? $decodedResponse['errors'] : json_encode($decodedResponse['errors']));
            } elseif (isset($decodedResponse['error'])) {
                $errorMsg .= ': ' . (is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']));
            }

            return ['error' => $errorMsg, 'raw_response' => $response];
        }

        // Obtener el token (dar prioridad a access_token como en LigoQRController)
        $token = $decodedResponse['data']['access_token'] ?? $decodedResponse['data']['token'];

        log_message('debug', 'Ligo Auth: Token received successfully');
        return ['token' => $token];
    }

    public function getAccountBalance($debtorCCI)
    {
        $data = [
            'debtorCCI' => $debtorCCI
        ];

        return $this->makeApiRequest('/v1/accountBalance', 'POST', $data);
    }

    public function getAccountBalanceForOrganization()
    {
        log_message('debug', 'LigoModel: Getting account balance using centralized config');
        
        // We still need organization for context
        $organization = $this->getOrganizationFromSession();
        if (!$organization) {
            log_message('error', 'LigoModel: No organization found in session');
            return ['error' => 'No organization found in session'];
        }

        log_message('debug', 'LigoModel: Organization context: ' . $organization['name'] . ' (ID: ' . $organization['id'] . ')');

        // Get centralized account_id from superadmin config
        $ligoConfig = $this->getSuperadminLigoConfig();
        if (!$ligoConfig) {
            log_message('error', 'LigoModel: No centralized Ligo configuration available');
            return ['error' => 'Configuración de Ligo no disponible. Contacte al administrador.'];
        }

        $accountId = $ligoConfig['account_id'];
        $environment = $ligoConfig['environment'];

        log_message('debug', 'LigoModel: Using centralized config - Environment: ' . $environment . ', Account ID: ' . ($accountId ?? 'null'));

        if (empty($accountId)) {
            log_message('error', 'LigoModel: No account ID configured in centralized config for environment: ' . $environment);
            return ['error' => "No account ID configured in centralized configuration for {$environment} environment"];
        }

        $data = [
            'debtorCCI' => $accountId
        ];
        
        log_message('debug', 'LigoModel: Making balance request with centralized config data: ' . json_encode($data));
        return $this->makeApiRequest('/v1/accountBalance', 'POST', $data);
    }

    public function listTransactions($params)
    {
        $data = [
            'page' => $params['page'] ?? 1,
            'startDate' => $params['startDate'],
            'endDate' => $params['endDate']
        ];

        if (!empty($params['debtorCCI'])) {
            $data['debtorCCI'] = $params['debtorCCI'];
        }

        if (!empty($params['creditorCCI'])) {
            $data['creditorCCI'] = $params['creditorCCI'];
        }

        return $this->makeApiRequest('/v1/transactionsReport', 'POST', $data);
    }

    public function listTransactionsForOrganization($params)
    {
        log_message('debug', 'LigoModel: Getting transactions using centralized config');
        
        // We still need organization for context
        $organization = $this->getOrganizationFromSession();
        if (!$organization) {
            log_message('error', 'LigoModel: No organization found in session');
            return ['error' => 'No organization found in session'];
        }

        log_message('debug', 'LigoModel: Organization context: ' . $organization['name'] . ' (ID: ' . $organization['id'] . ')');

        // Get centralized account_id from superadmin config
        $ligoConfig = $this->getSuperadminLigoConfig();
        if (!$ligoConfig) {
            log_message('error', 'LigoModel: No centralized Ligo configuration available');
            return ['error' => 'Configuración de Ligo no disponible. Contacte al administrador.'];
        }

        $accountId = $ligoConfig['account_id'];
        $environment = $ligoConfig['environment'];

        log_message('debug', 'LigoModel: Using centralized config - Environment: ' . $environment . ', Account ID: ' . ($accountId ?? 'null'));

        if (empty($accountId)) {
            log_message('error', 'LigoModel: No account ID configured in centralized config for environment: ' . $environment);
            return ['error' => "No account ID configured in centralized configuration for {$environment} environment"];
        }

        $data = [
            'page' => $params['page'] ?? 1,
            'startDate' => $params['startDate'],
            'endDate' => $params['endDate'],
            'debtorCCI' => $accountId  // Usar automáticamente el account_id centralizado
        ];

        log_message('debug', 'LigoModel: Making transactions request with centralized config data: ' . json_encode($data));
        return $this->makeApiRequest('/v1/transactionsReport', 'POST', $data);
    }

    public function getTransactionDetail($transactionId)
    {
        $data = [
            'transferId' => null,
            'instructionId' => $transactionId  // Usar instructionId como parámetro principal
        ];
        
        return $this->makeApiRequest('/v1/transactionsReportById', 'POST', $data);
    }

    public function listRecharges($params)
    {
        // Asegurar formato de fechas YYYY-MM-DD según ejemplo de documentación
        $startDate = $params['startDate'];
        $endDate = $params['endDate'];
        
        // Función para convertir fecha - probando formato YYYY-MM-DD basado en el ejemplo de la documentación
        $formatDate = function($date) {
            if (!$date) return '';
            
            // Si está en formato YYYY-MM-DD, mantenerlo (como en el ejemplo de la documentación)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }
            
            // Si está en formato YYYYMMDD, convertir a YYYY-MM-DD
            if (preg_match('/^\d{8}$/', $date)) {
                return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            }
            
            // Intentar parsear la fecha y convertirla a YYYY-MM-DD
            try {
                $dateObj = new \DateTime($date);
                return $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                return '';
            }
        };
        
        $startDate = $formatDate($startDate);
        $endDate = $formatDate($endDate);
        
        $data = [
            'page' => $params['page'] ?? 1,
            'startDate' => $startDate,  // Formato YYYY-MM-DD (según ejemplo en documentación)
            'endDate' => $endDate,      // Formato YYYY-MM-DD (según ejemplo en documentación)
            'empty' => false  // false para mostrar registros con data (no vacíos)
        ];

        log_message('debug', 'LigoModel: Making recharges request with data: ' . json_encode($data));
        $response = $this->makeApiRequest('/v1/transactionsReportReception', 'POST', $data);
        
        // Enriquecer respuesta con información de installments
        if (isset($response['data']['records']) && is_array($response['data']['records'])) {
            $response['data']['records'] = $this->enrichRechargesWithInstallments($response['data']['records']);
        }
        
        return $response;
    }

    /**
     * Enriquece las recargas con información de installments asociados
     */
    protected function enrichRechargesWithInstallments($recharges)
    {
        $db = \Config\Database::connect();
        
        foreach ($recharges as &$recharge) {
            // Buscar QR hash usando unstructuredInformation como id_qr
            $unstructuredInfo = $recharge['unstructuredInformation'] ?? '';
            
            if (!empty($unstructuredInfo)) {
                // Buscar en ligo_qr_hashes por id_qr
                $qrQuery = $db->table('ligo_qr_hashes')
                    ->select('ligo_qr_hashes.instalment_id, ligo_qr_hashes.hash, ligo_qr_hashes.description')
                    ->where('ligo_qr_hashes.id_qr', $unstructuredInfo)
                    ->get();
                
                $qrResult = $qrQuery->getRowArray();
                
                if ($qrResult && !empty($qrResult['instalment_id'])) {
                    // Buscar información del instalment con invoice y cliente
                    $instalmentQuery = $db->table('instalments i')
                        ->select('i.id, i.uuid, i.invoice_id, i.number, i.amount, i.due_date, i.status, i.notes, 
                                 inv.invoice_number, inv.uuid as invoice_uuid, inv.concept, inv.total_amount, inv.currency, inv.client_id,
                                 c.business_name as client_business_name, c.contact_name as client_contact_name')
                        ->join('invoices inv', 'i.invoice_id = inv.id', 'left')
                        ->join('clients c', 'inv.client_id = c.id', 'left')
                        ->where('i.id', $qrResult['instalment_id'])
                        ->get();
                    
                    $instalmentResult = $instalmentQuery->getRowArray();
                    
                    if ($instalmentResult) {
                        // Agregar información del instalment a la recarga
                        $recharge['instalment'] = [
                            'id' => $instalmentResult['id'],
                            'uuid' => $instalmentResult['uuid'],
                            'invoice_id' => $instalmentResult['invoice_id'],
                            'invoice_number' => $instalmentResult['invoice_number'] ?? 'N/A',
                            'client_name' => $instalmentResult['client_business_name'] ?? 'Cliente sin nombre',
                            'client_contact' => $instalmentResult['client_contact_name'] ?? '',
                            'invoice_description' => $instalmentResult['concept'] ?? 'Pago de cuota',
                            'invoice_total' => $instalmentResult['total_amount'] ?? $instalmentResult['amount'],
                            'currency' => $instalmentResult['currency'] ?? 'PEN',
                            'number' => $instalmentResult['number'],
                            'amount' => $instalmentResult['amount'],
                            'due_date' => $instalmentResult['due_date'],
                            'status' => $instalmentResult['status'],
                            'notes' => $instalmentResult['notes']
                        ];
                        
                        $recharge['qr_info'] = [
                            'hash' => $qrResult['hash'],
                            'description' => $qrResult['description']
                        ];
                    }
                }
            }
        }
        
        return $recharges;
    }

    public function processOrdinaryTransfer($transferData)
    {
        try {
            // Paso 1: Consulta de cuenta
            $accountInquiryData = [
                'debtorParticipantCode' => $transferData['debtorParticipantCode'],
                'creditorParticipantCode' => $transferData['creditorParticipantCode'],
                'debtorName' => $transferData['debtorName'],
                'debtorId' => $transferData['debtorId'],
                'debtorIdCode' => $transferData['debtorIdCode'],
                'debtorPhoneNumber' => '',
                'debtorAddressLine' => $transferData['debtorAddressLine'],
                'debtorMobileNumber' => $transferData['debtorMobileNumber'],
                'transactionType' => '320',
                'channel' => '15',
                'creditorAddressLine' => 'JR LIMA',
                'creditorCCI' => $transferData['creditorCCI'],
                'debtorTypeOfPerson' => 'N',
                'currency' => $transferData['currency'] === 'PEN' ? '604' : '840'
            ];

            $step1Response = $this->makeApiRequest('/v1/accountInquiry', 'POST', $accountInquiryData);
            
            if (isset($step1Response['error'])) {
                return ['error' => 'Error en consulta de cuenta: ' . $step1Response['error']];
            }

            $accountInquiryId = $step1Response['data']['id'] ?? null;
            
            if (!$accountInquiryId) {
                return ['error' => 'No se recibió ID de consulta de cuenta'];
            }

            // Paso 2: Obtener respuesta de consulta
            sleep(2); // Esperar un momento para que se procese
            $step2Response = $this->makeApiRequest('/v1/getAccountInquiryById/' . $accountInquiryId, 'GET');
            
            if (isset($step2Response['error'])) {
                return ['error' => 'Error al obtener respuesta de consulta: ' . $step2Response['error']];
            }

            // Paso 3: Obtener código de comisión
            $feeData = [
                'debtorCCI' => $transferData['debtorCCI'] ?? $step2Response['data']['debtorCCI'],
                'creditorCCI' => $transferData['creditorCCI'],
                'currency' => $transferData['currency'],
                'amount' => $transferData['amount']
            ];

            $step3Response = $this->makeApiRequest('/v1/infoFeeCodeNew', 'POST', $feeData);
            
            if (isset($step3Response['error'])) {
                return ['error' => 'Error al obtener código de comisión: ' . $step3Response['error']];
            }

            // Paso 4: Ejecutar transferencia
            $transferOrderData = [
                'debtorParticipantCode' => $transferData['debtorParticipantCode'],
                'creditorParticipantCode' => $transferData['creditorParticipantCode'],
                'messageTypeId' => $step2Response['data']['messageTypeId'] ?? '320',
                'channel' => '15',
                'amount' => $transferData['amount'],
                'currency' => $transferData['currency'] === 'PEN' ? '604' : '840',
                'referenceTransactionId' => $step2Response['data']['instructionId'] ?? uniqid(),
                'transactionType' => '320',
                'feeAmount' => $step3Response['data']['feeAmount'] ?? 0,
                'feeCode' => $step3Response['data']['feeCode'] ?? '',
                'applicationCriteria' => $step3Response['data']['applicationCriteria'] ?? '',
                'debtorTypeOfPerson' => 'N',
                'debtorName' => $transferData['debtorName'],
                'debtorAddressLine' => $transferData['debtorAddressLine'],
                'debtorIdCode' => $transferData['debtorIdCode'],
                'debtorId' => $transferData['debtorId'],
                'debtorMobileNumber' => $transferData['debtorMobileNumber'],
                'debtorCCI' => $transferData['debtorCCI'] ?? $step2Response['data']['debtorCCI'],
                'creditorName' => $step2Response['data']['creditorName'] ?? 'Beneficiario',
                'creditorCCI' => $transferData['creditorCCI'],
                'sameCustomerFlag' => 'M',
                'purposeCode' => '001',
                'unstructuredInformation' => $transferData['unstructuredInformation'] ?? 'Transferencia ordinaria',
                'feeId' => $step3Response['data']['feeId'] ?? '',
                'feeLigo' => $step3Response['data']['feeLigo'] ?? ''
            ];

            $step4Response = $this->makeApiRequest('/v1/orderTransferShipping', 'POST', $transferOrderData);
            
            if (isset($step4Response['error'])) {
                return ['error' => 'Error al ejecutar transferencia: ' . $step4Response['error']];
            }

            $transferId = $step4Response['data']['id'] ?? null;
            
            if (!$transferId) {
                return ['error' => 'No se recibió ID de transferencia'];
            }

            // Paso 5: Obtener respuesta de transferencia
            sleep(3); // Esperar un momento para que se procese
            $step5Response = $this->makeApiRequest('/v1/getOrderTransferShippingById/' . $transferId, 'GET');
            
            if (isset($step5Response['error'])) {
                return ['error' => 'Error al obtener respuesta de transferencia: ' . $step5Response['error']];
            }

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'account_inquiry_id' => $accountInquiryId,
                'status' => $step5Response['data']['status'] ?? 'pending',
                'details' => $step5Response['data'],
                'steps' => [
                    'account_inquiry' => $step1Response,
                    'account_inquiry_response' => $step2Response,
                    'fee_code' => $step3Response,
                    'transfer_order' => $step4Response,
                    'transfer_response' => $step5Response
                ]
            ];

        } catch (\Exception $e) {
            log_message('error', 'Error en transferencia ordinaria: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    /**
     * Process ordinary transfer from superadmin account to organization
     * Uses centralized superadmin Ligo configuration
     */
    public function processOrdinaryTransferFromSuperadmin($transferData, $superadminConfig, $organization)
    {
        try {
            // Los datos del deudor vienen de la configuración del superadmin
            $debtorData = [
                'participantCode' => $superadminConfig['debtor_participant_code'] ?? '0123',
                'name' => $superadminConfig['debtor_name'] ?? 'CobraPepe SuperAdmin',
                'id' => $superadminConfig['debtor_id'] ?? '20123456789',
                'idCode' => $superadminConfig['debtor_id_code'] ?? '6',
                'addressLine' => $superadminConfig['debtor_address_line'] ?? 'Av. Javier Prado Este 123, San Isidro, Lima',
                'mobileNumber' => $superadminConfig['debtor_mobile_number'] ?? '999999999'
            ];

            // Verificar que los datos del deudor estén completos
            $requiredDebtorFields = ['participantCode', 'name', 'id', 'idCode', 'addressLine'];
            foreach ($requiredDebtorFields as $field) {
                if (empty($debtorData[$field])) {
                    return ['error' => "Configuración incompleta: falta campo de deudor '{$field}' en configuración del superadmin"];
                }
            }
            
            // Los datos del acreedor vienen de la organización
            $creditorData = [
                'participantCode' => '0049',  // Fixed creditor participant code for Mi Banco
                'cci' => $organization['cci'],
                'name' => $organization['name']
            ];

            // Paso 1: Consulta de cuenta
            $accountInquiryData = [
                'debtorParticipantCode' => $debtorData['participantCode'],
                'creditorParticipantCode' => $creditorData['participantCode'],
                'debtorName' => $debtorData['name'],
                'debtorId' => $debtorData['id'],
                'debtorIdCode' => $debtorData['idCode'],
                'debtorPhoneNumber' => '',
                'debtorAddressLine' => $debtorData['addressLine'],
                'debtorMobileNumber' => $debtorData['mobileNumber'],
                'transactionType' => '320',
                'channel' => '15',
                'creditorAddressLine' => 'JR LIMA',
                'creditorCCI' => $creditorData['cci'],
                'debtorTypeOfPerson' => 'N',
                'currency' => $transferData['currency'] === 'PEN' ? '604' : '840'
            ];

            $step1Response = $this->makeApiRequest('/v1/accountInquiry', 'POST', $accountInquiryData);
            
            if (isset($step1Response['error'])) {
                return ['error' => 'Error en consulta de cuenta: ' . $step1Response['error']];
            }

            $accountInquiryId = $step1Response['data']['id'] ?? null;
            
            if (!$accountInquiryId) {
                return ['error' => 'No se recibió ID de consulta de cuenta'];
            }

            // Paso 2: Obtener respuesta de consulta
            sleep(2); // Esperar un momento para que se procese
            $step2Response = $this->makeApiRequest('/v1/getAccountInquiryById/' . $accountInquiryId, 'GET');
            
            if (isset($step2Response['error'])) {
                return ['error' => 'Error al obtener respuesta de consulta: ' . $step2Response['error']];
            }

            // Obtener CCI del deudor de la respuesta (cuenta del superadmin)
            $debtorCCI = $step2Response['data']['debtorCCI'] ?? null;
            if (!$debtorCCI) {
                return ['error' => 'No se pudo obtener CCI del deudor desde la respuesta de consulta'];
            }

            // Paso 3: Obtener código de comisión
            $feeData = [
                'debtorCCI' => $debtorCCI,
                'creditorCCI' => $creditorData['cci'],
                'currency' => $transferData['currency'],
                'amount' => $transferData['amount']
            ];

            $step3Response = $this->makeApiRequest('/v1/infoFeeCodeNew', 'POST', $feeData);
            
            if (isset($step3Response['error'])) {
                return ['error' => 'Error al obtener código de comisión: ' . $step3Response['error']];
            }

            // Paso 4: Ejecutar transferencia
            $transferOrderData = [
                'debtorParticipantCode' => $debtorData['participantCode'],
                'creditorParticipantCode' => $creditorData['participantCode'],
                'messageTypeId' => $step2Response['data']['messageTypeId'] ?? '320',
                'channel' => '15',
                'amount' => $transferData['amount'],
                'currency' => $transferData['currency'] === 'PEN' ? '604' : '840',
                'referenceTransactionId' => $step2Response['data']['instructionId'] ?? uniqid(),
                'transactionType' => '320',
                'feeAmount' => $step3Response['data']['feeAmount'] ?? 0,
                'feeCode' => $step3Response['data']['feeCode'] ?? '',
                'applicationCriteria' => $step3Response['data']['applicationCriteria'] ?? '',
                'debtorTypeOfPerson' => 'N',
                'debtorName' => $debtorData['name'],
                'debtorAddressLine' => $debtorData['addressLine'],
                'debtorIdCode' => $debtorData['idCode'],
                'debtorId' => $debtorData['id'],
                'debtorMobileNumber' => $debtorData['mobileNumber'],
                'debtorCCI' => $debtorCCI,
                'creditorName' => $creditorData['name'],
                'creditorCCI' => $creditorData['cci'],
                'sameCustomerFlag' => 'M',
                'purposeCode' => '001',
                'unstructuredInformation' => $transferData['unstructuredInformation'] ?? 'Pago de comisiones a organización',
                'feeId' => $step3Response['data']['feeId'] ?? '',
                'feeLigo' => $step3Response['data']['feeLigo'] ?? ''
            ];

            $step4Response = $this->makeApiRequest('/v1/orderTransferShipping', 'POST', $transferOrderData);
            
            if (isset($step4Response['error'])) {
                return ['error' => 'Error al ejecutar transferencia: ' . $step4Response['error']];
            }

            $transferId = $step4Response['data']['id'] ?? null;
            
            if (!$transferId) {
                return ['error' => 'No se recibió ID de transferencia'];
            }

            // Paso 5: Obtener respuesta de transferencia
            sleep(3); // Esperar un momento para que se procese
            $step5Response = $this->makeApiRequest('/v1/getOrderTransferShippingById/' . $transferId, 'GET');
            
            if (isset($step5Response['error'])) {
                return ['error' => 'Error al obtener respuesta de transferencia: ' . $step5Response['error']];
            }

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'account_inquiry_id' => $accountInquiryId,
                'organization_id' => $transferData['organization_id'],
                'status' => $step5Response['data']['status'] ?? 'pending',
                'details' => $step5Response['data'],
                'steps' => [
                    'account_inquiry' => $step1Response,
                    'account_inquiry_response' => $step2Response,
                    'fee_code' => $step3Response,
                    'transfer_order' => $step4Response,
                    'transfer_response' => $step5Response
                ]
            ];

        } catch (\Exception $e) {
            log_message('error', 'Error en transferencia ordinaria desde superadmin: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    public function getTransferStatus($transferId)
    {
        return $this->makeApiRequest('/v1/getOrderTransferShippingById/' . $transferId, 'GET');
    }

    /**
     * Step 1: Perform Account Inquiry
     * Verifies the destination account exists
     */
    public function performAccountInquiry($superadminConfig, $organization, $creditorCCI, $currency)
    {
        try {
            // Build debtor data from superadmin config
            $debtorData = [
                'participantCode' => $superadminConfig['debtor_participant_code'] ?? '0123',
                'name' => $superadminConfig['debtor_name'] ?? 'CobraPepe SuperAdmin',
                'id' => $superadminConfig['debtor_id'] ?? '20123456789',
                'idCode' => $superadminConfig['debtor_id_code'] ?? '6',
                'addressLine' => $superadminConfig['debtor_address_line'] ?? 'Av. Javier Prado Este 123, San Isidro, Lima',
                'mobileNumber' => $superadminConfig['debtor_mobile_number'] ?? '999999999'
            ];

            // Validate debtor data
            $requiredDebtorFields = ['participantCode', 'name', 'id', 'idCode', 'addressLine'];
            foreach ($requiredDebtorFields as $field) {
                if (empty($debtorData[$field])) {
                    return ['error' => "Configuración incompleta: falta campo de deudor '{$field}' en configuración del superadmin"];
                }
            }

            // Build creditor data - use the CCI provided by user (destination account)
            $finalCreditorCCI = $creditorCCI;
            $creditorData = [
                'participantCode' => '0049',  // Fixed creditor participant code for Mi Banco
                'cci' => $finalCreditorCCI,
                'name' => $organization['name'] ?? 'Cuenta Destino'
            ];

            // Account Inquiry API call
            $accountInquiryData = [
                'debtorParticipantCode' => $debtorData['participantCode'],
                'creditorParticipantCode' => $creditorData['participantCode'],
                'debtorName' => $debtorData['name'],
                'debtorId' => $debtorData['id'],
                'debtorIdCode' => $debtorData['idCode'],
                'debtorPhoneNumber' => $superadminConfig['debtor_phone_number'] ?? '',
                'debtorAddressLine' => $debtorData['addressLine'],
                'debtorMobileNumber' => $debtorData['mobileNumber'],
                'transactionType' => $superadminConfig['transaction_type'] ?? '320',
                'channel' => $superadminConfig['channel'] ?? '15',
                'creditorAddressLine' => $superadminConfig['creditor_address_line'] ?? 'JR LIMA',
                'creditorCCI' => $creditorData['cci'],
                'debtorTypeOfPerson' => $superadminConfig['debtor_type_of_person'] ?? 'N',
                'currency' => $currency === 'PEN' ? '604' : '840'
            ];

            log_message('info', 'LigoModel: Performing account inquiry for CCI: ' . $finalCreditorCCI);
            log_message('debug', 'LigoModel: AccountInquiry data: ' . json_encode($accountInquiryData));
            
            $response = $this->makeApiRequest('/v1/accountInquiry', 'POST', $accountInquiryData);
            
            log_message('debug', 'LigoModel: AccountInquiry response: ' . json_encode($response));
            
            if (isset($response['error'])) {
                return ['error' => 'Error en consulta de cuenta: ' . $response['error']];
            }

            $accountInquiryId = $response['data']['id'] ?? null;
            
            if (!$accountInquiryId) {
                return ['error' => 'No se recibió ID de consulta de cuenta'];
            }

            return [
                'success' => true,
                'accountInquiryId' => $accountInquiryId,
                'debtorData' => $debtorData,
                'creditorData' => $creditorData,
                'rawResponse' => $response
            ];

        } catch (\Exception $e) {
            log_message('error', 'Error en performAccountInquiry: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    /**
     * Step 2: Get Account Inquiry Result by ID
     * Gets the result of the account verification
     */
    public function getAccountInquiryResult($accountInquiryId)
    {
        try {
            log_message('info', 'LigoModel: Getting account inquiry result for ID: ' . $accountInquiryId);
            
            // Increased delay to allow processing (based on successful test command)
            sleep(3);
            
            // Try multiple times with delays like the successful test command
            $endpoint = '/v1/getAccountInquiryById/' . $accountInquiryId;
            $response = null;
            $lastError = null;
            $maxAttempts = 3;
            
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                log_message('debug', 'LigoModel: Attempt ' . $attempt . '/' . $maxAttempts . ' for endpoint: ' . $endpoint);
                
                if ($attempt > 1) {
                    sleep(3); // Longer delay between attempts - production might be slower
                }
                
                $response = $this->makeApiRequest($endpoint, 'GET');
                
                if (isset($response['error'])) {
                    $lastError = $response['error'];
                    log_message('debug', 'LigoModel: Attempt ' . $attempt . ' failed: ' . $lastError);
                    continue;
                }
                
                // Check if we have valid data
                $data = $response['data'] ?? [];
                log_message('debug', 'LigoModel: Attempt ' . $attempt . ' response data: ' . json_encode($data));
                
                // Check if data contains actual account information
                if (!empty($data) && (isset($data['debtorCCI']) || isset($data['creditorCCI']) || isset($data['creditorName']))) {
                    log_message('info', 'LigoModel: Success on attempt ' . $attempt . ' with account data');
                    break;
                } elseif (empty($data) || (is_array($data) && count($data) === 0)) {
                    log_message('debug', 'LigoModel: Attempt ' . $attempt . ' returned empty data, retrying...');
                    $lastError = 'Empty data response after ' . $attempt . ' attempts';
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                } else {
                    log_message('info', 'LigoModel: Success on attempt ' . $attempt . ' (partial data)');
                    break;
                }
            }
            
            if (isset($response['error']) || empty($response)) {
                return ['error' => 'Error al obtener respuesta de consulta: ' . ($lastError ?? 'Unknown error')];
            }

            // Extract important information
            $data = $response['data'] ?? [];
            log_message('debug', 'LigoModel: getAccountInquiryResult - Raw response data: ' . json_encode($data));
            
            // Try different data extraction methods
            $debtorCCI = null;
            $creditorCCI = null;
            $creditorName = 'Nombre no disponible';
            $messageTypeId = '320';
            $instructionId = uniqid();
            
            if (is_array($data) && !empty($data)) {
                // Direct field access
                $debtorCCI = $data['debtorCCI'] ?? null;
                $creditorCCI = $data['creditorCCI'] ?? null;
                $creditorName = $data['creditorName'] ?? $creditorName;
                $messageTypeId = $data['messageTypeId'] ?? $messageTypeId;
                $instructionId = $data['instructionId'] ?? $instructionId;
                
                // If data is array of objects, try first element
                if (empty($debtorCCI) && isset($data[0]) && is_array($data[0])) {
                    $firstItem = $data[0];
                    $debtorCCI = $firstItem['debtorCCI'] ?? null;
                    $creditorCCI = $firstItem['creditorCCI'] ?? null;
                    $creditorName = $firstItem['creditorName'] ?? $creditorName;
                    $messageTypeId = $firstItem['messageTypeId'] ?? $messageTypeId;
                    $instructionId = $firstItem['instructionId'] ?? $instructionId;
                }
            }

            log_message('debug', 'LigoModel: getAccountInquiryResult - Extracted values: debtorCCI=' . ($debtorCCI ?? 'NULL') . ', creditorName=' . $creditorName . ', messageTypeId=' . $messageTypeId);

            if (!$debtorCCI) {
                log_message('warning', 'LigoModel: getAccountInquiryResult - debtorCCI is empty. Full response: ' . json_encode($response));
                
                // Fallback: use account_id from superadmin config as debtorCCI
                $ligoConfig = $this->getSuperadminLigoConfig();
                if ($ligoConfig && !empty($ligoConfig['account_id'])) {
                    $debtorCCI = $ligoConfig['account_id'];
                    log_message('info', 'LigoModel: Using fallback debtorCCI from config: ' . $debtorCCI);
                } else {
                    log_message('error', 'LigoModel: No fallback debtorCCI available. Full response: ' . json_encode($response));
                    return ['error' => 'No se pudo obtener CCI del deudor desde la respuesta de consulta'];
                }
            }

            return [
                'success' => true,
                'debtorCCI' => $debtorCCI,
                'creditorName' => $creditorName,
                'messageTypeId' => $messageTypeId,
                'instructionId' => $instructionId,
                'rawResponse' => $response
            ];

        } catch (\Exception $e) {
            log_message('error', 'Error en getAccountInquiryResult: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    /**
     * Step 3: Calculate Transfer Fee
     * Gets fee information for the transfer
     */
    public function calculateTransferFee($debtorCCI, $creditorCCI, $amount, $currency)
    {
        try {
            log_message('info', 'LigoModel: Calculating transfer fee for amount: ' . $amount . ' ' . $currency);
            
            // Validate CCI formats
            if (strlen($debtorCCI) !== 20) {
                log_message('warning', 'LigoModel: debtorCCI has invalid length: ' . strlen($debtorCCI) . ' (should be 20). Value: ' . $debtorCCI);
            }
            if (strlen($creditorCCI) !== 20) {
                log_message('warning', 'LigoModel: creditorCCI has invalid length: ' . strlen($creditorCCI) . ' (should be 20). Value: ' . $creditorCCI);
            }
            
            $feeData = [
                'debtorCCI' => $debtorCCI,
                'creditorCCI' => $creditorCCI,
                'currency' => $currency === 'PEN' ? '604' : '840',
                'amount' => (float)$amount
            ];
            
            log_message('debug', 'LigoModel: Fee calculation data: ' . json_encode($feeData));

            $response = $this->makeApiRequest('/v1/infoFeeCodeNew', 'POST', $feeData);
            
            if (isset($response['error'])) {
                return ['error' => 'Error al obtener código de comisión: ' . $response['error']];
            }

            $data = $response['data'] ?? [];
            
            // Map API response fields correctly
            $feeAmount = $data['feeAmount'] ?? $data['feeTotal'] ?? 0;
            $feeCode = $data['feeCode'] ?? $data['rateCode'] ?? '';
            $applicationCriteria = !empty($data['applicationCriteria']) ? $data['applicationCriteria'] : 'M';
            $feeId = $data['feeId'] ?? $data['id'] ?? '';
            $feeLigo = $data['feeLigo'] ?? 0;

            log_message('debug', 'LigoModel: Fee calculation mapped values: feeAmount=' . $feeAmount . ', feeCode=' . $feeCode . ', feeId=' . $feeId);

            return [
                'success' => true,
                'feeAmount' => $feeAmount,
                'feeCode' => $feeCode,
                'applicationCriteria' => $applicationCriteria,
                'feeId' => $feeId,
                'feeLigo' => $feeLigo,
                'totalAmount' => (float)$amount + (float)$feeAmount,
                'rawResponse' => $response
            ];

        } catch (\Exception $e) {
            log_message('error', 'Error en calculateTransferFee: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    /**
     * Step 4: Execute Transfer
     * Performs the final transfer with all validated data
     */
    public function executeTransfer($superadminConfig, $organization, $transferData)
    {
        try {
            log_message('info', 'LigoModel: Executing transfer for amount: ' . $transferData['amount']);
            log_message('debug', 'LigoModel: executeTransfer input data: ' . json_encode($transferData));
            
            // Build debtor data from superadmin config
            $debtorData = [
                'participantCode' => $superadminConfig['debtor_participant_code'] ?? '0921',
                'name' => $superadminConfig['debtor_name'] ?? 'CobraPepe SuperAdmin',
                'id' => $superadminConfig['debtor_id'] ?? '20123456789',
                'idCode' => $superadminConfig['debtor_id_code'] ?? '6',
                'addressLine' => $superadminConfig['debtor_address_line'] ?? 'Av. Javier Prado Este 123, San Isidro, Lima',
                'mobileNumber' => $superadminConfig['debtor_mobile_number'] ?? '999999999'
            ];

            // Build creditor data
            $creditorData = [
                'participantCode' => '0049',  // Fixed creditor participant code for Mi Banco
                'cci' => $transferData['creditorCCI'],
                'name' => $organization['name']
            ];

            // Convert amounts to required format (multiply by 100 for 2 decimal places)
            $amountFormatted = intval(floatval($transferData['amount']) * 100);
            $feeAmountFormatted = intval(floatval($transferData['feeAmount']) * 100);
            
            // Execute transfer with complete API payload matching Ligo documentation exactly
            $transferOrderData = [
                'debtorParticipantCode' => (string)$debtorData['participantCode'],
                'creditorParticipantCode' => (string)$creditorData['participantCode'],
                'messageTypeId' => (string)($transferData['messageTypeId'] ?? '0200'),
                'channel' => (string)($superadminConfig['channel'] ?? '15'),
                'amount' => $amountFormatted,
                'currency' => (string)($transferData['currency'] === 'PEN' ? '604' : '840'),
                'referenceTransactionId' => (string)($transferData['instructionId'] ?? date('YmdHis') . rand(100000, 999999)),
                'transactionType' => (string)'320',
                'feeAmount' => $feeAmountFormatted,
                'feeCode' => (string)($transferData['feeCode'] ?? ''),
                'applicationCriteria' => (string)(!empty($transferData['applicationCriteria']) ? $transferData['applicationCriteria'] : 'M'),
                'debtorTypeOfPerson' => (string)($superadminConfig['debtor_type_of_person'] ?? 'N'),
                'debtorName' => (string)$debtorData['name'],
                'debtorAddressLine' => (string)$debtorData['addressLine'],
                'debtorId' => (string)$debtorData['id'],
                'debtorIdCode' => (string)$debtorData['idCode'],
                'debtorMobileNumber' => (string)$debtorData['mobileNumber'],
                'debtorCCI' => (string)($transferData['debtorCCI'] ?? $superadminConfig['account_id'] ?? ''),
                'creditorName' => (string)$creditorData['name'],
                'creditorAddressLine' => (string)($superadminConfig['creditor_address_line'] ?? 'JR LIMA'),
                'creditorCCI' => (string)$creditorData['cci'],
                'sameCustomerFlag' => (string)'O',
                'purposeCode' => (string)($transferData['purposeCode'] ?? '0105'),
                'unstructuredInformation' => (string)($transferData['unstructuredInformation'] ?? 'Transferencia Ordinaria'),
                'feeId' => (string)($transferData['feeId'] ?? ''),
                'feeLigo' => (string)($transferData['feeLigo'] ?? '0')
            ];

            log_message('info', 'LigoModel: Sending transfer order with data: ' . json_encode($transferOrderData));
            
            // Log data types for debugging
            $dataTypes = [];
            foreach ($transferOrderData as $key => $value) {
                $dataTypes[$key] = gettype($value) . ' (' . $value . ')';
            }
            log_message('debug', 'LigoModel: Payload data types: ' . json_encode($dataTypes));

            $response = $this->makeApiRequest('/v1/orderTransferShipping', 'POST', $transferOrderData);
            
            log_message('debug', 'LigoModel: executeTransfer API response: ' . json_encode($response));
            
            if (isset($response['error'])) {
                log_message('error', 'LigoModel: executeTransfer API error: ' . $response['error']);
                return ['error' => 'Error al ejecutar transferencia: ' . $response['error']];
            }

            // Check if response has the expected structure from successful API calls
            if (isset($response['status']) && $response['status'] == 1 && isset($response['data'])) {
                // Successful response similar to the example provided
                $responseData = $response['data'];
                
                $transferId = $responseData['instructionId'] ?? $responseData['id'] ?? null;
                
                if (!$transferId) {
                    log_message('error', 'LigoModel: No transfer ID found in response: ' . json_encode($response));
                    return ['error' => 'No se recibió ID de transferencia en la respuesta'];
                }

                log_message('info', 'LigoModel: Transfer executed successfully with ID: ' . $transferId);

                return [
                    'success' => true,
                    'transferId' => $transferId,
                    'status' => 'completed',
                    'retrievalReferenceNumber' => $responseData['retrievalReferenteNumber'] ?? '',
                    'trace' => $responseData['trace'] ?? '',
                    'transactionReference' => $responseData['transactionReference'] ?? '',
                    'responseCode' => $responseData['responseCode'] ?? '',
                    'settlementDate' => $responseData['settlementDate'] ?? '',
                    'debtorCCI' => $responseData['debtorCCI'] ?? '',
                    'creditorCCI' => $responseData['creditorCCI'] ?? '',
                    'interbankSettlementAmount' => $responseData['interbankSettlementAmount'] ?? 0,
                    'transferResponse' => $response
                ];
            } else {
                // Fallback for different response structure
                $transferId = $response['data']['id'] ?? null;
                
                if (!$transferId) {
                    log_message('error', 'LigoModel: Unexpected response structure: ' . json_encode($response));
                    return ['error' => 'Respuesta inesperada de la API - no se encontró ID de transferencia'];
                }

                // Get transfer status
                sleep(3);
                $statusResponse = $this->makeApiRequest('/v1/getOrderTransferShippingById/' . $transferId, 'GET');

                return [
                    'success' => true,
                    'transferId' => $transferId,
                    'status' => $statusResponse['data']['status'] ?? 'pending',
                    'transferResponse' => $response,
                    'statusResponse' => $statusResponse
                ];
            }

        } catch (\Exception $e) {
            log_message('error', 'Error en executeTransfer: ' . $e->getMessage());
            return ['error' => 'Error interno: ' . $e->getMessage()];
        }
    }
}