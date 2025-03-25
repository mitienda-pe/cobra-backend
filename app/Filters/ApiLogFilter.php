<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use Config\Services;

class ApiLogFilter implements FilterInterface
{
    /**
     * Log the API request
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get request data
        $requestData = [
            'uri' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'ip' => $request->getIPAddress(),
            'headers' => $this->getHeadersArray($request),
            'body' => $this->getRequestBody($request),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Store request data in session for after filter
        session()->set('api_request_data', $requestData);
    }

    /**
     * Log the API response
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Get request data from session
        $requestData = session()->get('api_request_data');
        if (!$requestData) return;

        // Clear session data
        session()->remove('api_request_data');

        // Get response data
        $responseData = [
            'status_code' => $response->getStatusCode(),
            'headers' => $this->getHeadersArray($response),
            'body' => $response->getBody()
        ];

        // Prepare log data
        $logData = [
            'request' => $requestData,
            'response' => $responseData
        ];

        // Add user data if available
        $user = session()->get('api_user');
        if ($user) {
            $logData['user_id'] = $user['id'];
            $logData['organization_id'] = $user['organization_id'];
        }

        // Format log message
        $logMessage = sprintf(
            "[API] %s %s | Status: %d | User: %s | IP: %s | Body: %s | Response: %s",
            $requestData['method'],
            $requestData['uri'],
            $responseData['status_code'],
            $user['id'] ?? 'guest',
            $requestData['ip'],
            json_encode($requestData['body']),
            $responseData['body']
        );

        // Log to file
        log_message('info', $logMessage);
    }

    /**
     * Get headers as array from request or response
     */
    private function getHeadersArray($obj): array
    {
        $headers = [];
        foreach ($obj->headers() as $name => $value) {
            $headers[$name] = $value->getValue();
        }
        return $headers;
    }

    /**
     * Get request body data
     */
    private function getRequestBody(RequestInterface $request)
    {
        if (!$request instanceof IncomingRequest) {
            return null;
        }

        $contentType = $request->getHeaderLine('Content-Type');
        
        if (strpos($contentType, 'application/json') !== false) {
            $raw = $request->getBody();
            return json_decode($raw, true) ?? $raw;
        }
        
        return $request->getRawInput() ?? $request->getBody();
    }
}
