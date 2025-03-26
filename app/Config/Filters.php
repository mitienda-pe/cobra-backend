<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;
use App\Filters\CorsFilter;
use App\Filters\AuthFilter;
use App\Filters\ApiAuthFilter;
use App\Filters\ApiLogFilter;

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
        'cors'          => CorsFilter::class,
        'auth'          => AuthFilter::class,
        'apiAuth'       => ApiAuthFilter::class,
        'apiLog'        => ApiLogFilter::class,
        // Combined filter aliases
        'api-public'    => ['cors'],
        'api-auth'      => ['cors', 'apiAuth', 'apiLog'],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            'honeypot',
            'csrf' => ['except' => [
                'api/*',
                'api',
                'clients/import*',
                'invoices/import*',
                'login*',
                'logout'
            ]],
            'invalidchars',
        ],
        'after' => [
            'toolbar',
            'honeypot',
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
        'auth' => [
            'before' => ['dashboard', 'dashboard/*', 'organizations/*', 'clients/*', 'invoices/*', 'users/*', 'profile/*', 'portfolios/*', 'payments/*', 'webhooks/*'],
            'except' => [
                'clients/import*',
                'invoices/import*'
            ]
        ],
    ];
}