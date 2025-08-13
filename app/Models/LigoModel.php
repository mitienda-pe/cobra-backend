<?php

namespace App\Models;

use CodeIgniter\Model;

class LigoModel extends Model
{
    protected $organizationModel;
    protected $ligoBaseUrl;
    protected $ligoAuthUrl;

    public function __construct()
    {
        parent::__construct();
        $this->organizationModel = new OrganizationModel();
        
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

    protected function makeApiRequest($endpoint, $method = 'GET', $data = null, $requiresAuth = true)
    {
        $organization = $this->getOrganizationFromSession();
        
        if (!$organization) {
            log_message('error', 'LigoModel: No organization available for API request');
            return ['error' => 'No hay organización seleccionada'];
        }

        // Verificar credenciales según el entorno configurado en la organización
        $environment = env('CI_ENVIRONMENT', 'development');
        $orgEnvironment = $organization['ligo_environment'] ?? 'dev';
        
        // Si estamos en producción, usar las credenciales de producción configuradas en la organización
        // Si estamos en desarrollo, usar las credenciales según el entorno configurado en la organización
        if ($environment === 'production' || $orgEnvironment === 'prod') {
            $requiredFields = ['ligo_prod_username', 'ligo_prod_password', 'ligo_prod_company_id'];
            $useEnv = 'prod';
        } else {
            $requiredFields = ['ligo_dev_username', 'ligo_dev_password', 'ligo_dev_company_id'];
            $useEnv = 'dev';
        }

        foreach ($requiredFields as $field) {
            if (empty($organization[$field])) {
                log_message('error', 'LigoModel: Missing credential field: ' . $field . ' for useEnv: ' . $useEnv);
                return ['error' => 'Credenciales de Ligo no configuradas para ' . $useEnv . '. Falta: ' . $field];
            }
        }
        
        log_message('debug', 'LigoModel: Using environment: ' . $environment . ', org environment: ' . $orgEnvironment . ', final env: ' . $useEnv . ' with URL: ' . $this->ligoBaseUrl);
        
        // Ajustar URLs según el entorno que se va a usar
        if ($useEnv === 'prod') {
            $this->ligoBaseUrl = 'https://cce-api-gateway-prod.ligocloud.tech';
            $this->ligoAuthUrl = 'https://cce-auth-prod.ligocloud.tech';
        } else {
            $this->ligoBaseUrl = 'https://cce-api-gateway-dev.ligocloud.tech';
            $this->ligoAuthUrl = 'https://cce-auth-dev.ligocloud.tech';
        }

        $curl = curl_init();
        $url = $this->ligoBaseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($requiresAuth) {
            $token = $this->getAuthToken($organization);
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

    protected function getAuthToken($organization)
    {
        // Usar credenciales según el entorno de la organización
        $orgEnvironment = $organization['ligo_environment'] ?? 'dev';
        log_message('debug', 'LigoModel: Auth using environment: ' . $orgEnvironment);
        
        if ($orgEnvironment === 'prod') {
            $authData = [
                'username' => $organization['ligo_prod_username'],
                'password' => $organization['ligo_prod_password']
            ];
            $companyId = $organization['ligo_prod_company_id'];
            $privateKey = $organization['ligo_prod_private_key'] ?? null;
            $useEnv = 'prod';
            log_message('debug', 'LigoModel: Using PROD credentials for auth');
        } else {
            $authData = [
                'username' => $organization['ligo_dev_username'],
                'password' => $organization['ligo_dev_password']
            ];
            $companyId = $organization['ligo_dev_company_id'];
            $privateKey = $organization['ligo_dev_private_key'] ?? null;
            $useEnv = 'dev';
            log_message('debug', 'LigoModel: Using DEV credentials for auth');
        }

        log_message('debug', 'LigoModel: Auth data - username: ' . ($authData['username'] ?? 'null') . ', company_id: ' . ($companyId ?? 'null'));

        // Validar que las credenciales estén presentes
        if (empty($authData['username']) || empty($authData['password']) || empty($companyId)) {
            log_message('error', 'LigoModel: Missing auth credentials for environment: ' . $orgEnvironment);
            return ['error' => "Missing authentication credentials for {$orgEnvironment} environment"];
        }

        // Verificar que la clave privada exista
        if (empty($privateKey)) {
            log_message('error', 'LigoModel: Private key not configured for environment: ' . $orgEnvironment);
            return ['error' => "Private key not configured for {$orgEnvironment} environment"];
        }

        // Ajustar URL de auth según el entorno
        if ($useEnv === 'prod') {
            $authBaseUrl = 'https://cce-auth-prod.ligocloud.tech';
        } else {
            $authBaseUrl = 'https://cce-auth-dev.ligocloud.tech';
        }
        
        $authUrl = $authBaseUrl . '/v1/auth/sign-in?companyId=' . $companyId;
        log_message('debug', 'LigoModel: Authenticating with URL: ' . $authUrl . ' and username: ' . $authData['username'] . ' for env: ' . $useEnv);

        // Generar token JWT usando la clave privada (mismo método que LigoQRController)
        try {
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);

            // Preparar payload
            $payload = [
                'companyId' => $companyId
            ];

            // Generar token JWT
            $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600 // 1 hora
            ]);

            log_message('info', 'LigoModel: JWT token generated successfully');
            log_message('debug', 'LigoModel: JWT token: ' . substr($authorizationToken, 0, 30) . '...');
        } catch (\Exception $e) {
            log_message('error', 'LigoModel: Error generating JWT token: ' . $e->getMessage());
            return ['error' => 'Error generating JWT token: ' . $e->getMessage()];
        }

        $curl = curl_init();
        $requestBody = json_encode($authData);

        curl_setopt_array($curl, [
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authorizationToken,  // JWT token como LigoQRController
                'Content-Type: application/json',
                'Content-Length: ' . strlen($requestBody),
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

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
        log_message('debug', 'LigoModel: Getting account balance for organization');
        
        $organization = $this->getOrganizationFromSession();
        if (!$organization) {
            log_message('error', 'LigoModel: No organization found in session');
            return ['error' => 'No organization found in session'];
        }

        log_message('debug', 'LigoModel: Organization found: ' . $organization['name'] . ' (ID: ' . $organization['id'] . ')');

        // Determinar el entorno y obtener el account_id correspondiente
        $environment = $organization['ligo_environment'] ?? 'dev';
        $accountId = $organization["ligo_{$environment}_account_id"] ?? null;

        log_message('debug', 'LigoModel: Environment: ' . $environment . ', Account ID: ' . ($accountId ?? 'null'));

        if (empty($accountId)) {
            log_message('error', 'LigoModel: No account ID configured for environment: ' . $environment);
            return ['error' => "No account ID configured for {$environment} environment"];
        }

        $data = [
            'debtorCCI' => $accountId
        ];
        
        log_message('debug', 'LigoModel: Making balance request with data: ' . json_encode($data));
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
        log_message('debug', 'LigoModel: Getting transactions for organization');
        
        $organization = $this->getOrganizationFromSession();
        if (!$organization) {
            log_message('error', 'LigoModel: No organization found in session');
            return ['error' => 'No organization found in session'];
        }

        log_message('debug', 'LigoModel: Organization found: ' . $organization['name'] . ' (ID: ' . $organization['id'] . ')');

        // Determinar el entorno y obtener el account_id correspondiente
        $environment = $organization['ligo_environment'] ?? 'dev';
        $accountId = $organization["ligo_{$environment}_account_id"] ?? null;

        log_message('debug', 'LigoModel: Environment: ' . $environment . ', Account ID: ' . ($accountId ?? 'null'));

        if (empty($accountId)) {
            log_message('error', 'LigoModel: No account ID configured for environment: ' . $environment);
            return ['error' => "No account ID configured for {$environment} environment"];
        }

        $data = [
            'page' => $params['page'] ?? 1,
            'startDate' => $params['startDate'],
            'endDate' => $params['endDate'],
            'debtorCCI' => $accountId  // Usar automáticamente el account_id de la organización
        ];

        log_message('debug', 'LigoModel: Making transactions request with data: ' . json_encode($data));
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
                    // Buscar información del instalment
                    $instalmentQuery = $db->table('instalments i')
                        ->select('i.id, i.uuid, i.invoice_id, i.number, i.amount, i.due_date, i.status, i.notes, inv.invoice_number, inv.client_name, inv.description as invoice_description')
                        ->join('invoices inv', 'i.invoice_id = inv.id', 'left')
                        ->where('i.id', $qrResult['instalment_id'])
                        ->get();
                    
                    $instalmentResult = $instalmentQuery->getRowArray();
                    
                    if ($instalmentResult) {
                        // Agregar información del instalment a la recarga
                        $recharge['instalment'] = [
                            'id' => $instalmentResult['id'],
                            'uuid' => $instalmentResult['uuid'],
                            'invoice_id' => $instalmentResult['invoice_id'],
                            'invoice_number' => $instalmentResult['invoice_number'],
                            'client_name' => $instalmentResult['client_name'],
                            'invoice_description' => $instalmentResult['invoice_description'],
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

    public function getTransferStatus($transferId)
    {
        return $this->makeApiRequest('/v1/getOrderTransferShippingById/' . $transferId, 'GET');
    }
}