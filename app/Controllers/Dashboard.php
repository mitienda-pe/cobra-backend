<?php

namespace App\Controllers;

use App\Libraries\Auth;

class Dashboard extends BaseController
{
    protected $auth;
    
    public function __construct()
    {
        $this->auth = new Auth();
    }
    
    public function index()
    {
        $data = [
            'user' => $this->auth->user(),
        ];
        
        // Different dashboard layouts based on role
        $role = $this->auth->user()['role'];
        
        if ($role == 'superadmin') {
            return view('dashboard/superadmin', $data);
        } elseif ($role == 'admin') {
            return view('dashboard/admin', $data);
        } else {
            return view('dashboard/user', $data);
        }
    }
}