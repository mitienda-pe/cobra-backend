<?php

namespace App\Libraries;

trait ApiResponse
{
    /**
     * Return unauthorized error
     */
    protected function failUnauthorized($message)
    {
        $response = service('response');
        $response->setStatusCode(401);
        $response->setJSON([
            'status' => false,
            'message' => $message,
            'error' => 'Unauthorized'
        ]);
        
        return $response;
    }

    /**
     * Return validation error
     */
    protected function failValidation($errors)
    {
        $response = service('response');
        $response->setStatusCode(422);
        $response->setJSON([
            'status' => false,
            'message' => 'Validation error',
            'errors' => $errors
        ]);
        
        return $response;
    }

    /**
     * Return success response
     */
    protected function respondSuccess($data = null, $message = 'Success')
    {
        $response = service('response');
        $response->setStatusCode(200);
        $response->setJSON([
            'status' => true,
            'message' => $message,
            'data' => $data
        ]);
        
        return $response;
    }
}
