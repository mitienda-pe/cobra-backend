<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Libraries\Auth as AuthLib;

class AuthController extends BaseController
{
    protected $auth;
    protected $session;

    public function __construct()
    {
        $this->auth = new AuthLib();
        $this->session = \Config\Services::session();
        helper(['form', 'url']);
    }

    public function login()
    {
        // If already logged in, redirect to dashboard
        if ($this->auth->check()) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        // Validate request
        if (!$this->validate([
            'email' => 'required|valid_email',
            'password' => 'required'
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        $remember = $this->request->getPost('remember') === 'on';

        if ($this->auth->attempt($email, $password)) {
            if ($remember) {
                $this->auth->setRememberToken($this->auth->user()['id']);
            }
            return redirect()->to('/dashboard');
        }

        return redirect()->back()->withInput()->with('error', 'Invalid credentials');
    }

    public function logout()
    {
        $this->auth->logout();
        return redirect()->to('/auth/login')->with('message', 'Successfully logged out');
    }
}
