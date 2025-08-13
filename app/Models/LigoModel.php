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

        // Verificar credenciales según el entorno
        $environment = env('CI_ENVIRONMENT', 'development');
        if ($environment === 'production') {
            $requiredFields = ['ligo_prod_username', 'ligo_prod_password', 'ligo_prod_company_id'];
        } else {
            $requiredFields = ['ligo_username', 'ligo_password', 'ligo_company_id'];
        }

        foreach ($requiredFields as $field) {
            if (empty($organization[$field])) {
                log_message('error', 'LigoModel: Missing credential field: ' . $field . ' for environment: ' . $environment);
                return ['error' => 'Credenciales de Ligo no configuradas para ' . $environment . '. Falta: ' . $field];
            }
        }
        
        log_message('debug', 'LigoModel: Using environment: ' . $environment . ' with URL: ' . $this->ligoBaseUrl);

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
            log_message('error', 'Ligo API Error: ' . $err);
            return ['error' => 'Error de conexión con Ligo API: ' . $err];
        }

        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['message'] ?? 'Error en la API de Ligo';
            log_message('error', 'Ligo API HTTP Error ' . $httpCode . ': ' . $response);
            return ['error' => $errorMessage, 'http_code' => $httpCode];
        }

        return $decodedResponse;
    }

    protected function getAuthToken($organization)
    {
        $curl = curl_init();

        // Usar credenciales según el entorno
        $environment = env('CI_ENVIRONMENT', 'development');
        if ($environment === 'production') {
            $authData = [
                'username' => $organization['ligo_prod_username'],
                'password' => $organization['ligo_prod_password']
            ];
            $companyId = $organization['ligo_prod_company_id'];
        } else {
            $authData = [
                'username' => $organization['ligo_username'],
                'password' => $organization['ligo_password']
            ];
            $companyId = $organization['ligo_company_id'];
        }

        $authUrl = $this->ligoAuthUrl . '/v1/auth/sign-in?companyId=' . $companyId;
        log_message('debug', 'LigoModel: Authenticating with URL: ' . $authUrl . ' and username: ' . $authData['username']);

        curl_setopt_array($curl, [
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($authData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
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
            log_message('error', 'Ligo Auth Error: ' . $err);
            return ['error' => 'Error de conexión con Ligo Auth: ' . $err];
        }

        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['message'] ?? 'Error de autenticación con Ligo';
            log_message('error', 'Ligo Auth HTTP Error ' . $httpCode . ': ' . $response);
            return ['error' => $errorMessage];
        }

        if (!isset($decodedResponse['data']['token'])) {
            return ['error' => 'Token de autenticación no recibido'];
        }

        return ['token' => $decodedResponse['data']['token']];
    }

    public function getAccountBalance($debtorCCI)
    {
        $data = [
            'debtorCCI' => $debtorCCI
        ];

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

        return $this->makeApiRequest('/v1/listTransactions', 'POST', $data);
    }

    public function getTransactionDetail($transactionId)
    {
        return $this->makeApiRequest('/v1/transaction/' . $transactionId, 'GET');
    }

    public function listRecharges($params)
    {
        $data = [
            'page' => $params['page'] ?? 1,
            'startDate' => $params['startDate'],
            'endDate' => $params['endDate'],
            'type' => 'recharge'
        ];

        return $this->makeApiRequest('/v1/listTransactions', 'POST', $data);
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