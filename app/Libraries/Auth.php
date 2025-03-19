<?php

namespace App\Libraries;

use App\Models\UserModel;

class Auth
{
    protected $session;
    
    public function __construct()
    {
        $this->session = session();
    }
    
    /**
     * Attempt to log in a user
     */
    public function attempt($email, $password)
    {
        $userModel = new UserModel();
        $user = $userModel->authenticate($email, $password);
        
        if (!$user) {
            return false;
        }
        
        $this->logUserIn($user);
        return true;
    }
    
    /**
     * Log the user in
     */
    protected function logUserIn($user)
    {
        // Remove password from session data
        unset($user['password']);
        
        $this->session->set('user', $user);
        $this->session->set('isLoggedIn', true);
        
        // Set remember token if needed
        if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
            $this->setRememberToken($user['id']);
        }
    }
    
    /**
     * Set remember token
     */
    protected function setRememberToken($userId)
    {
        $token = bin2hex(random_bytes(16));
        
        // Store token in database
        $userModel = new UserModel();
        $userModel->update($userId, ['remember_token' => $token]);
        
        // Set cookie for 30 days
        setcookie(
            'remember_token',
            $token,
            time() + (86400 * 30),
            '/',
            '',
            false,
            true
        );
    }
    
    /**
     * Check if user is logged in
     */
    public function check()
    {
        return $this->session->get('isLoggedIn') === true;
    }
    
    /**
     * Get current logged user
     */
    public function user()
    {
        return $this->session->get('user');
    }
    
    /**
     * Log out user
     */
    public function logout()
    {
        // Get user ID before clearing session
        $userId = $this->user()['id'] ?? null;
        
        // Clear session
        $this->session->remove(['user', 'isLoggedIn']);
        
        // Clear remember token
        if ($userId) {
            $userModel = new UserModel();
            $userModel->update($userId, ['remember_token' => null]);
        }
        
        // Clear remember cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    /**
     * Check if user has a specific role
     */
    public function hasRole($role)
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        
        return $user['role'] === $role;
    }
    
    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles)
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        
        return in_array($user['role'], $roles);
    }
    
    /**
     * Get user's organization ID
     */
    public function organizationId()
    {
        $user = $this->user();
        if (!$user) {
            return null;
        }
        
        return $user['organization_id'];
    }
}