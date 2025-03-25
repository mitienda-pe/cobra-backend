<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class BaseApiController extends Controller
{
    use ResponseTrait;

    protected $format = 'json';

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Force JSON response format
        $this->response->setContentType('application/json');
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
        
        // Handle preflight requests
        if ($this->request->getMethod(true) === 'OPTIONS') {
            $this->response->setStatusCode(200);
            $this->response->send();
            exit();
        }

        // If this is a web request (Accept: text/html), return 406 Not Acceptable
        $accept = $this->request->getHeaderLine('Accept');
        if (strpos($accept, 'text/html') !== false && strpos($accept, 'application/json') === false) {
            $this->response->setStatusCode(406);
            $this->response->setJSON([
                'status' => 'error',
                'message' => 'API endpoint only accepts application/json'
            ]);
            $this->response->send();
            exit();
        }
    }

    /**
     * Success Response
     */
    protected function successResponse($data = null, string $message = null, int $code = 200)
    {
        return $this->respond([
            'status' => 'success',
            'data' => $data,
            'message' => $message
        ], $code);
    }

    /**
     * Error Response
     */
    protected function errorResponse(string $message = null, int $code = 400, $data = null)
    {
        return $this->respond([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
