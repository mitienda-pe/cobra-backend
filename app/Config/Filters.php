<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'auth'          => \App\Filters\AuthFilter::class,
        'role'          => \App\Filters\RoleFilter::class,
        'apiAuth'       => \App\Filters\ApiAuthFilter::class,
        'organization'  => \App\Filters\OrganizationFilter::class,
        'csrfExcept'    => \App\Filters\CsrfExceptFilter::class,
        'disableCsrf'   => \App\Filters\DisableCsrfForRoutes::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            // 'honeypot',
            // 'csrf', // Moved to specific routes in $filters
            'invalidchars',
            'disableCsrf',  // IMPORTANT: This must come BEFORE the CSRF filter
            'organization', // Add organization filter globally
            'csrfExcept',   // Add our custom CSRF exception filter
        ],
        'after' => [
            'toolbar',
            // 'honeypot',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     */
    public array $filters = [
        // CSRF Filter - exclude import actions
        'csrf' => [
            'before' => ['*'],
            'except' => [
                // Complete list of routes to exclude from CSRF protection
                'clients/import',
                'clients/import/',
                '/clients/import',
                '/clients/import/',
                'invoices/import',
                'invoices/import/',
                '/invoices/import',
                '/invoices/import/'
            ]
        ],
        // API filters only
        'apiAuth' => [
            'before' => [
                'api/*',
                'api',
            ],
            'except' => [
                'api/auth/otp/request',
                'api/auth/otp/verify',
                'api/auth/refresh',
                'api/users',
                'api/clients',
                'debug/client-create',
                'debug/auth-info',
                'debug/get-users-by-organization/*',
                'debug/get-clients-by-organization/*',
            ]
        ],
        // Auth filter for web routes
        'auth' => [
            'before' => [
                'dashboard',
                'dashboard/*',
                'clients',
                'clients/*',
                'portfolios',
                'portfolios/*',
                'invoices',
                'invoices/*',
                'payments',
                'payments/*',
                'webhooks',
                'webhooks/*',
                'users',
                'users/*',
                'organizations',
                'organizations/*',
            ],
            'except' => [
                'auth/*',
                'auth',
            ]
        ]
    ];
}