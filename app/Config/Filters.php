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
        'apiLog'        => \App\Filters\ApiLogFilter::class,
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
            'invalidchars',
            'disableCsrf',  // IMPORTANT: This must come BEFORE the CSRF filter
            'csrfExcept',   // Add our custom CSRF exception filter
        ],
        'after' => [
            'toolbar',
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
        // CSRF Filter - exclude API routes
        'csrf' => [
            'before' => ['*'],
            'except' => [
                'api/*',
                'api'
            ]
        ],
        // API filters
        'apiAuth' => [
            'before' => [
                'api/*',
                'api'
            ],
            'except' => [
                'api/auth/request-otp',
                'api/auth/verify-otp',
                'api/auth/refresh-token'
            ]
        ],
        'apiLog' => [
            'before' => [
                'api/*',
                'api'
            ]
        ],
        // Web filters
        'auth' => [
            'before' => ['*'],
            'except' => [
                'api/*',
                'api',
                'auth/*',
                'auth'
            ]
        ],
        'organization' => [
            'before' => ['*'],
            'except' => [
                'api/*',
                'api',
                'auth/*',
                'auth'
            ]
        ]
    ];
}