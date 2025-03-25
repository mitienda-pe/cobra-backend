<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class BaseApiController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';

    public function __construct()
    {
        // Force JSON response format
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit();
        }

        // Force JSON response for all API requests
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            $this->response->setContentType('application/json');
            
            // If this is a web request (Accept: text/html), return 406 Not Acceptable
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (strpos($accept, 'text/html') !== false && strpos($accept, 'application/json') === false) {
                $this->response->setStatusCode(406);
                $this->response->setJSON([
                    'status' => false,
                    'message' => 'API endpoint only accepts application/json'
                ]);
                $this->response->send();
                exit();
            }
        }
    }

    /**
     * Success Response
     */
    protected function successResponse($data = null, string $message = null, int $code = 200)
    {
        return $this->respond([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Error Response
     */
    protected function errorResponse($message, int $code = 400, $errors = null)
    {
        return $this->respond([
            'status' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}
