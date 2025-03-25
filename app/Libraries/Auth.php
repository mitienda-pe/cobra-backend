<?php

namespace App\Libraries;

use App\Models\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    protected $session;
    protected $key;
    protected $algorithm;
    protected $tokenDuration;

    public function __construct()
    {
        $this->session = session();
        $this->key = getenv('JWT_SECRET_KEY');
        $this->algorithm = 'HS256';
        $this->tokenDuration = 86400; // 24 hours
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
     * For superadmins, use the selected organization ID from session if available
     */
    public function organizationId()
    {
        $user = $this->user();
        if (!$user) {
            return null;
        }

        // For superadmins, check if there's a selected organization in the session
        if ($user['role'] === 'superadmin') {
            $selectedOrgId = $this->session->get('selected_organization_id');

            // Debug log to help track the session value
            log_message('debug', '[Auth.organizationId] Superadmin check - Session selected_organization_id: ' .
                       ($selectedOrgId ? $selectedOrgId : 'null'));

            if ($selectedOrgId) {
                return $selectedOrgId;
            }
        }

        // For other users, return their assigned organization
        log_message('debug', '[Auth.organizationId] Using user\'s assigned organization: ' . $user['organization_id']);
        return $user['organization_id'];
    }

    /**
     * Get user's fixed organization ID (ignoring session selection)
     */
    public function fixedOrganizationId()
    {
        $user = $this->user();
        if (!$user) {
            return null;
        }

        return $user['organization_id'];
    }

    /**
     * Generate JWT token
     */
    public function generateToken($userId)
    {
        $time = time();

        $payload = [
            'iss' => 'cobra_api',
            'aud' => 'cobra_client',
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + $this->tokenDuration,
            'user_id' => $userId
        ];

        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    /**
     * Validate JWT token
     */
    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            log_message('error', '[Auth] Token validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken($token)
    {
        try {
            $decoded = $this->validateToken($token);
            if (!$decoded) {
                return false;
            }

            return $this->generateToken($decoded['user_id']);
        } catch (\Exception $e) {
            log_message('error', '[Auth] Token refresh error: ' . $e->getMessage());
            return false;
        }
    }
}