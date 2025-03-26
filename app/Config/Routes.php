<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// Public Routes (No auth required)
$routes->get('/', 'Home::index');
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::login');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('auth/forgot-password', 'Auth::forgot_password');
$routes->post('auth/forgot-password', 'Auth::forgot_password');
$routes->get('auth/reset-password/(:segment)', 'Auth::reset_password/$1');
$routes->post('auth/reset-password/(:segment)', 'Auth::reset_password/$1');

// Debug routes
$routes->get('debug/client-create', 'Debug::clientCreate');
$routes->post('debug/client-create', 'Debug::clientCreate');
$routes->get('debug/auth-info', 'Debug::authInfo');
$routes->get('debug/get-users-by-organization/(:num)', 'Debug::getUsersByOrganization/$1');
$routes->get('debug/get-clients-by-organization/(:num)', 'Debug::getClientsByOrganization/$1');
$routes->get('debug/orgContext', 'Debug::orgContext');
$routes->get('debug/csrf', 'Debug::csrf');
$routes->get('debug/db-test', 'Debug::dbTest');
$routes->get('debug/test-api', 'Debug::testApi');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Debug endpoints - useful for troubleshooting
    $routes->match(['get', 'post', 'options'], 'debug', 'AuthController::debug', ['filter' => 'cors']);
    $routes->match(['get', 'post', 'options'], 'test-otp', 'AuthController::testOtp', ['filter' => 'cors']);
    
    // Auth Public Routes
    $routes->post('auth/request-otp', 'AuthController::requestOtp', ['filter' => 'cors']);
    $routes->post('auth/verify-otp', 'AuthController::verifyOtp', ['filter' => 'cors']);
    $routes->post('auth/refresh-token', 'AuthController::refreshToken', ['filter' => 'cors']);
    // OPTIONS routes for CORS preflight
    $routes->match(['options'], 'auth/request-otp', 'AuthController::requestOtp', ['filter' => 'cors']);
    $routes->match(['options'], 'auth/verify-otp', 'AuthController::verifyOtp', ['filter' => 'cors']);
    $routes->match(['options'], 'auth/refresh-token', 'AuthController::refreshToken', ['filter' => 'cors']);
    
    // Client Routes
    $routes->match(['get', 'options'], 'clients', 'ClientController::index', ['filter' => 'cors']);
    $routes->match(['post', 'options'], 'clients/create', 'ClientController::create', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'clients/(:segment)', 'ClientController::show/$1', ['filter' => 'cors']);
    $routes->match(['put', 'options'], 'clients/(:segment)', 'ClientController::update/$1', ['filter' => 'cors']);
    $routes->match(['delete', 'options'], 'clients/(:segment)', 'ClientController::delete/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'clients/uuid/(:segment)', 'ClientController::findByUuid/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'clients/external/(:segment)', 'ClientController::findByExternalId/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'clients/document/(:segment)', 'ClientController::findByDocument/$1', ['filter' => 'cors']);
    
    // Portfolio Routes
    $routes->match(['get', 'options'], 'portfolios', 'PortfolioController::index', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'portfolios/(:segment)', 'PortfolioController::show/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'portfolios/my', 'PortfolioController::myPortfolios', ['filter' => 'cors']);

    // Invoice Routes
    $routes->match(['get', 'options'], 'invoices', 'InvoiceController::index', ['filter' => 'cors']);
    $routes->match(['post', 'options'], 'invoices', 'InvoiceController::create', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'invoices/(:num)', 'InvoiceController::show/$1', ['filter' => 'cors']);
    $routes->match(['put', 'options'], 'invoices/(:num)', 'InvoiceController::update/$1', ['filter' => 'cors']);
    $routes->match(['delete', 'options'], 'invoices/(:num)', 'InvoiceController::delete/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'invoices/external/(:segment)', 'InvoiceController::findByExternalId/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'invoices/overdue', 'InvoiceController::overdue', ['filter' => 'cors']);

    // User Routes
    $routes->match(['get', 'options'], 'users', 'UserController::index', ['filter' => 'cors']);
    // $routes->match(['post', 'options'], 'users', 'UserController::create');
    $routes->match(['get', 'options'], 'users/(:num)', 'UserController::show/$1', ['filter' => 'cors']);
    $routes->match(['put', 'options'], 'users/(:num)', 'UserController::update/$1', ['filter' => 'cors']);
    $routes->match(['delete', 'options'], 'users/(:num)', 'UserController::delete/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'users/portfolio/(:num)', 'UserController::byPortfolio/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'user/profile', 'UserController::profile', ['filter' => 'cors']);

    // Payment Routes
    $routes->match(['get', 'options'], 'payments', 'PaymentController::index', ['filter' => 'cors']);
    $routes->match(['post', 'options'], 'payments', 'PaymentController::create', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'payments/(:num)', 'PaymentController::show/$1', ['filter' => 'cors']);
    $routes->match(['put', 'options'], 'payments/(:num)', 'PaymentController::update/$1', ['filter' => 'cors']);
    $routes->match(['delete', 'options'], 'payments/(:num)', 'PaymentController::delete/$1', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'payments/external/(:segment)', 'PaymentController::findByExternalId/$1', ['filter' => 'cors']);

    // Organization Routes
    $routes->match(['get', 'options'], 'organizations', 'OrganizationController::index', ['filter' => 'cors']);
    $routes->match(['post', 'options'], 'organizations', 'OrganizationController::create', ['filter' => 'cors']);
    $routes->match(['get', 'options'], 'organizations/(:segment)', 'OrganizationController::show/$1', ['filter' => 'cors']);
    $routes->match(['put', 'options'], 'organizations/(:segment)', 'OrganizationController::update/$1', ['filter' => 'cors']);
    $routes->match(['delete', 'options'], 'organizations/(:segment)', 'OrganizationController::delete/$1', ['filter' => 'cors']);
});

// API Routes - Protected 
$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'api-auth'], function ($routes) {
    // Auth Protected Routes
    $routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');
});

// Web Routes - Protected
$routes->group('', ['namespace' => 'App\Controllers', 'filter' => 'api-auth'], function ($routes) {
    // Dashboard Routes
    $routes->get('dashboard', 'Dashboard::index');

    // Organization Routes
    $routes->group('organizations', function($routes) {
        $routes->get('/', 'OrganizationController::index');
        $routes->get('create', 'OrganizationController::create');
        $routes->post('/', 'OrganizationController::store');
        $routes->get('(:segment)', 'OrganizationController::view/$1');
        $routes->get('(:segment)/edit', 'OrganizationController::edit/$1');
        $routes->post('(:segment)', 'OrganizationController::update/$1');
        $routes->post('(:segment)/delete', 'OrganizationController::delete/$1');
    });

    // User Routes
    $routes->group('users', function($routes) {
        $routes->get('/', 'UserController::index');
        $routes->get('create', 'UserController::create');
        $routes->post('/', 'UserController::store');
        $routes->post('(:segment)/delete', 'UserController::delete/$1');
        $routes->post('(:segment)', 'UserController::update/$1');
        $routes->get('(:segment)/edit', 'UserController::edit/$1');
        $routes->get('(:segment)', 'UserController::view/$1');
    });

    // Client Routes
    $routes->group('clients', function($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('create', 'ClientController::store');
        $routes->get('(:segment)', 'ClientController::view/$1');
        $routes->get('(:segment)/edit', 'ClientController::edit/$1');
        $routes->post('(:segment)', 'ClientController::update/$1');
        $routes->post('(:segment)/delete', 'ClientController::delete/$1');
        // Client import routes (with CSRF bypass)
        $routes->get('import', 'ClientController::import', ['csrf' => false]);
        $routes->post('import', 'ClientController::import', ['csrf' => false]);
    });

    // Invoice Routes
    $routes->group('invoices', function($routes) {
        $routes->get('/', 'InvoiceController::index');
        $routes->get('create', 'InvoiceController::create');
        $routes->post('/', 'InvoiceController::store');
        $routes->get('(:num)', 'InvoiceController::view/$1');
        $routes->get('(:num)/edit', 'InvoiceController::edit/$1');
        $routes->post('(:num)', 'InvoiceController::update/$1');
        $routes->get('(:num)/delete', 'InvoiceController::delete/$1');
        // Invoice import routes (with CSRF bypass)
        $routes->get('import', 'InvoiceController::import', ['csrf' => false]);
        $routes->post('import', 'InvoiceController::import', ['csrf' => false]);
    });

    // Portfolio Routes
    $routes->group('portfolios', function($routes) {
        $routes->get('/', 'PortfolioController::index');
        $routes->get('create', 'PortfolioController::create');
        $routes->post('create', 'PortfolioController::create');
        $routes->get('(:segment)', 'PortfolioController::view/$1');
        $routes->get('(:segment)/edit', 'PortfolioController::edit/$1');
        $routes->post('(:segment)/edit', 'PortfolioController::edit/$1');
        $routes->get('(:segment)/delete', 'PortfolioController::delete/$1');
        $routes->get('organization/(:uuid)/users', 'PortfolioController::getUsersByOrganization/$1');
        $routes->get('organization/(:uuid)/clients', 'PortfolioController::getClientsByOrganization/$1');
    });

    // Payment Routes
    $routes->group('payments', function($routes) {
        $routes->get('/', 'PaymentController::index');
        $routes->get('create', 'PaymentController::create');
        $routes->post('/', 'PaymentController::store');
        $routes->get('(:num)', 'PaymentController::view/$1');
        $routes->get('(:num)/edit', 'PaymentController::edit/$1');
        $routes->post('(:num)', 'PaymentController::update/$1');
        $routes->get('(:num)/delete', 'PaymentController::delete/$1');
        $routes->get('report', 'PaymentController::report');
    });

    // Webhook Routes
    $routes->group('webhooks', function($routes) {
        $routes->get('/', 'WebhookController::index');
        $routes->get('create', 'WebhookController::create');
        $routes->post('/', 'WebhookController::store');
        $routes->get('(:num)/edit', 'WebhookController::edit/$1');
        $routes->post('(:num)', 'WebhookController::update/$1');
        $routes->get('(:num)/delete', 'WebhookController::delete/$1');
        $routes->get('(:num)/logs', 'WebhookController::logs/$1');
        $routes->get('(:num)/test', 'WebhookController::test/$1');
        $routes->get('(:num)/retry', 'WebhookController::retry/$1');
    });
});