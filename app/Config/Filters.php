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
use App\Filters\SuperadminRedirectFilter;

class Filters extends BaseConfig
{
    public array $aliases = [
        'csrf'              => CSRF::class,
        'toolbar'           => DebugToolbar::class,
        'honeypot'          => Honeypot::class,
        'invalidchars'      => InvalidChars::class,
        'secureheaders'     => SecureHeaders::class,
        'cors'              => CorsFilter::class,
        'auth'              => AuthFilter::class,
        'apiAuth'           => ApiAuthFilter::class,
        'apiLog'            => ApiLogFilter::class,
        'superadminRedirect' => SuperadminRedirectFilter::class,
    ];

    public array $globals = [
        'before' => [
            'honeypot',
            'csrf' => ['except' => [
                'api/*',
                'api',
                'clients/import*',
                'invoices/import*',
                'payment/ligo/*',
                'ligo/hashes',
                'webhooks/ligo*',
                'login*',
                'logout'
            ]],
        ],
        'after' => [
            'toolbar',
            'honeypot',
            'secureheaders',
        ],
    ];

    public array $methods = [];

    public array $filters = [
        'cors' => [
            'before' => ['api/*', 'api'],
        ],
        'apiAuth' => [
            'before' => [
                'api/clients*',
                'api/portfolios*',
                'api/invoices*',
                'api/users*',
                'api/payments*',
                'api/organizations*',
                'api/auth/logout'
            ]
        ],
        'auth' => [
            'before' => [
                'dashboard',
                'dashboard/*',
                'organizations/*',
                'clients/*',
                'invoices/*',
                'users/*',
                'profile/*',
                'portfolios/*',
                'payments/*'
            ],
            'except' => [
                'clients/import*',
                'invoices/import*'
            ]
        ],
        'superadminRedirect' => [
            'before' => [
                'dashboard',
                'dashboard/*',
                'clients/*',
                'invoices/*',
                'users/*',
                'portfolios/*',
                'payments/*',
                'instalments/*'
            ]
        ],
    ];
}