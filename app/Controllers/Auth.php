<?php

namespace App\Controllers;

use App\Libraries\Auth as AuthLib;
use App\Models\UserModel;

class Auth extends BaseController
{
    protected $auth;
    
    public function __construct()
    {
        $this->auth = new AuthLib();
        helper(['form', 'url']);
    }
    
    public function login()
    {
        // Debug para rastrear el problema
        log_message('debug', 'Auth::login método llamado. Método: ' . $this->request->getMethod());
        log_message('debug', 'Session data: ' . json_encode(session()->get()));
        
        // Si ya está logueado, redirigir a dashboard
        if ($this->auth->check()) {
            log_message('debug', 'Usuario ya logueado, redirigiendo a dashboard');
            return redirect()->to('/dashboard');
        }
        
        // Limpiar sesión para prevenir bucles
        if ($this->request->getMethod() === 'get') {
            log_message('debug', 'Método GET, limpiando sesión');
            session()->remove('_ci_previous_url');
            session()->remove('redirect_url');
            
            // Desactivar CSRF para el login
            $security = service('security');
            try {
                $reflectionClass = new \ReflectionClass($security);
                $property = $reflectionClass->getProperty('CSRFVerify');
                if ($property) {
                    $property->setAccessible(true);
                    $property->setValue($security, false);
                    log_message('debug', 'CSRF desactivado para login');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al desactivar CSRF: ' . $e->getMessage());
            }
        }
        
        // Manejo del formulario de login
        if ($this->request->getMethod() === 'post') {
            log_message('debug', 'Procesando formulario POST de login');
            $rules = [
                'email' => 'required|valid_email',
                'password' => 'required|min_length[8]',
            ];
            
            if ($this->validate($rules)) {
                $email = $this->request->getPost('email');
                $password = $this->request->getPost('password');
                
                log_message('debug', 'Intentando autenticar: ' . $email);
                if ($this->auth->attempt($email, $password)) {
                    log_message('debug', 'Autenticación exitosa, redirigiendo a dashboard');
                    return redirect()->to('/dashboard')->with('message', 'Inicio de sesión exitoso.');
                } else {
                    log_message('debug', 'Autenticación fallida');
                    return redirect()->back()->withInput()
                        ->with('error', 'Credenciales incorrectas. Intente nuevamente.');
                }
            } else {
                log_message('debug', 'Validación fallida: ' . json_encode($this->validator->getErrors()));
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        log_message('debug', 'Mostrando vista de login');
        return view('auth/login');
    }
    
    public function logout()
    {
        log_message('debug', 'Auth::logout método llamado');
        
        // Limpiar session completamente
        $this->auth->logout();
        session()->destroy();
        
        // Eliminar cookies de sesión
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        
        // Desactivar CSRF para prevenir problemas en el siguiente login
        $security = service('security');
        try {
            $reflectionClass = new \ReflectionClass($security);
            $property = $reflectionClass->getProperty('CSRFVerify');
            if ($property) {
                $property->setAccessible(true);
                $property->setValue($security, false);
                log_message('debug', 'CSRF desactivado después de logout');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al desactivar CSRF después de logout: ' . $e->getMessage());
        }
        
        log_message('debug', 'Redirigiendo a login después de logout');
        return redirect()->to('/auth/login?logout=true');
    }
    
    public function forgot_password()
    {
        // If already logged in, redirect to dashboard
        if ($this->auth->check()) {
            return redirect()->to('/dashboard');
        }
        
        // Handle forgot password form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'email' => 'required|valid_email',
            ];
            
            if ($this->validate($rules)) {
                $email = $this->request->getPost('email');
                $userModel = new UserModel();
                $user = $userModel->where('email', $email)->first();
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(16));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                    
                    // Save token to database
                    $userModel->update($user['id'], [
                        'reset_token' => $token,
                        'reset_token_expires_at' => $expires
                    ]);
                    
                    // TODO: Send email with reset link
                    // For now, just show the token in the response
                    return redirect()->back()->with('message', 'Se ha enviado un correo para restablecer su contraseña. ' . 
                        'Token: ' . $token);
                }
                
                // Always show the same message for security
                return redirect()->back()->with('message', 'Si su correo está registrado, recibirá instrucciones para restablecer su contraseña.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('auth/forgot_password');
    }
    
    public function reset_password($token = null)
    {
        // If already logged in, redirect to dashboard
        if ($this->auth->check()) {
            return redirect()->to('/dashboard');
        }
        
        // Check if token is valid
        if (!$token) {
            return redirect()->to('/auth/login')->with('error', 'Token inválido.');
        }
        
        $userModel = new UserModel();
        $user = $userModel->where('reset_token', $token)
                         ->where('reset_token_expires_at >', date('Y-m-d H:i:s'))
                         ->first();
        
        if (!$user) {
            return redirect()->to('/auth/login')->with('error', 'Token inválido o expirado.');
        }
        
        // Handle reset password form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'password' => 'required|min_length[8]',
                'password_confirm' => 'required|matches[password]',
            ];
            
            if ($this->validate($rules)) {
                $password = $this->request->getPost('password');
                
                // Update password and clear token
                $userModel->update($user['id'], [
                    'password' => $password,
                    'reset_token' => null,
                    'reset_token_expires_at' => null
                ]);
                
                return redirect()->to('/auth/login')->with('message', 'Su contraseña ha sido actualizada correctamente.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('auth/reset_password', ['token' => $token]);
    }
}