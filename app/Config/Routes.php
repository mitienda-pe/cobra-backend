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

// API Routes - Public (sin ningún filtro)
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Auth API Routes
    $routes->match(['post', 'options'], 'auth/request-otp', 'AuthController::requestOtp');
    $routes->match(['post', 'options'], 'auth/verify-otp', 'AuthController::verifyOtp');
    $routes->match(['post', 'options'], 'auth/refresh-token', 'AuthController::refreshToken');
});

// API Routes - Protected 
$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors apiAuth apiLog'], function ($routes) {
    // Auth Protected Routes
    $routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');
    
    // Client Routes
    $routes->match(['get', 'options'], 'clients', 'ClientController::index');
    $routes->match(['post', 'options'], 'clients', 'ClientController::create');
    $routes->match(['get', 'options'], 'clients/(:num)', 'ClientController::show/$1');
    $routes->match(['put', 'options'], 'clients/(:num)', 'ClientController::update/$1');
    $routes->match(['delete', 'options'], 'clients/(:num)', 'ClientController::delete/$1');
    $routes->match(['get', 'options'], 'clients/uuid/(:segment)', 'ClientController::findByUuid/$1');
    $routes->match(['get', 'options'], 'clients/external/(:segment)', 'ClientController::findByExternalId/$1');
    $routes->match(['get', 'options'], 'clients/document/(:segment)', 'ClientController::findByDocument/$1');
    
    // Portfolio Routes
    $routes->match(['get', 'options'], 'portfolios', 'PortfolioController::index');
    $routes->match(['get', 'options'], 'portfolios/(:num)', 'PortfolioController::show/$1');
    $routes->match(['get', 'options'], 'portfolios/my', 'PortfolioController::myPortfolios');

    // Invoice Routes
    $routes->match(['get', 'options'], 'invoices', 'InvoiceController::index');
    $routes->match(['post', 'options'], 'invoices', 'InvoiceController::create');
    $routes->match(['get', 'options'], 'invoices/(:num)', 'InvoiceController::show/$1');
    $routes->match(['put', 'options'], 'invoices/(:num)', 'InvoiceController::update/$1');
    $routes->match(['delete', 'options'], 'invoices/(:num)', 'InvoiceController::delete/$1');
    $routes->match(['get', 'options'], 'invoices/external/(:segment)', 'InvoiceController::findByExternalId/$1');
    $routes->match(['get', 'options'], 'invoices/overdue', 'InvoiceController::overdue');

    // User Routes
    $routes->match(['get', 'options'], 'users', 'UserController::index');
    $routes->match(['post', 'options'], 'users', 'UserController::create');
    $routes->match(['get', 'options'], 'users/(:num)', 'UserController::show/$1');
    $routes->match(['put', 'options'], 'users/(:num)', 'UserController::update/$1');
    $routes->match(['delete', 'options'], 'users/(:num)', 'UserController::delete/$1');
    $routes->match(['get', 'options'], 'users/portfolio/(:num)', 'UserController::byPortfolio/$1');
    $routes->match(['get', 'options'], 'user/profile', 'UserController::profile');

    // Payment Routes
    $routes->match(['get', 'options'], 'payments', 'PaymentController::index');
    $routes->match(['post', 'options'], 'payments', 'PaymentController::create');
    $routes->match(['get', 'options'], 'payments/(:num)', 'PaymentController::show/$1');
    $routes->match(['put', 'options'], 'payments/(:num)', 'PaymentController::update/$1');
    $routes->match(['delete', 'options'], 'payments/(:num)', 'PaymentController::delete/$1');
    $routes->match(['get', 'options'], 'payments/external/(:segment)', 'PaymentController::findByExternalId/$1');

    // Organization Routes
    $routes->match(['get', 'options'], 'organizations', 'OrganizationController::index');
    $routes->match(['post', 'options'], 'organizations', 'OrganizationController::create');
    $routes->match(['get', 'options'], 'organizations/(:num)', 'OrganizationController::show/$1');
    $routes->match(['put', 'options'], 'organizations/(:num)', 'OrganizationController::update/$1');
    $routes->match(['delete', 'options'], 'organizations/(:num)', 'OrganizationController::delete/$1');
});

// Web Routes - Protected
$routes->group('', ['filter' => 'auth'], function ($routes) {
    // Dashboard Routes
    $routes->get('dashboard', 'Dashboard::index');

    // Organization Routes
    $routes->get('organizations', 'OrganizationController::index');
    $routes->get('organizations/create', 'OrganizationController::create');
    $routes->post('organizations/create', 'OrganizationController::create');
    $routes->get('organizations/edit/(:num)', 'OrganizationController::edit/$1');
    $routes->post('organizations/edit/(:num)', 'OrganizationController::edit/$1');
    $routes->get('organizations/delete/(:num)', 'OrganizationController::delete/$1');
    $routes->get('organizations/view/(:num)', 'OrganizationController::view/$1');

    // Client Routes
    $routes->get('clients', 'ClientController::index');
    $routes->get('clients/create', 'ClientController::create');
    $routes->post('clients/create', 'ClientController::create');
    $routes->get('clients/edit/(:num)', 'ClientController::edit/$1');
    $routes->post('clients/edit/(:num)', 'ClientController::edit/$1');
    $routes->get('clients/delete/(:num)', 'ClientController::delete/$1');
    $routes->get('clients/view/(:num)', 'ClientController::view/$1');
    // Client import routes (with CSRF bypass)
    $routes->get('clients/import', 'ClientController::import', ['csrf' => false]);
    $routes->post('clients/import', 'ClientController::import', ['csrf' => false]);

    // Invoice Routes
    $routes->get('invoices', 'InvoiceController::index');
    $routes->get('invoices/create', 'InvoiceController::create');
    $routes->post('invoices/create', 'InvoiceController::create');
    $routes->get('invoices/edit/(:num)', 'InvoiceController::edit/$1');
    $routes->post('invoices/edit/(:num)', 'InvoiceController::edit/$1');
    $routes->get('invoices/delete/(:num)', 'InvoiceController::delete/$1');
    $routes->get('invoices/view/(:num)', 'InvoiceController::view/$1');
    // Invoice import routes (with CSRF bypass)
    $routes->get('invoices/import', 'InvoiceController::import', ['csrf' => false]);
    $routes->post('invoices/import', 'InvoiceController::import', ['csrf' => false]);

    // User Routes
    $routes->get('users', 'Users::index');
    $routes->get('users/create', 'Users::create');
    $routes->post('users/create', 'Users::create');
    $routes->get('users/edit/(:num)', 'Users::edit/$1');
    $routes->post('users/edit/(:num)', 'Users::edit/$1');
    $routes->get('users/delete/(:num)', 'Users::delete/$1');
    $routes->get('users/profile', 'Users::profile');
    $routes->post('users/profile', 'Users::profile');

    // Portfolio Routes
    $routes->get('portfolios', 'PortfolioController::index');
    $routes->get('portfolios/create', 'PortfolioController::create');
    $routes->post('portfolios/create', 'PortfolioController::create');
    $routes->get('portfolios/edit/(:num)', 'PortfolioController::edit/$1');
    $routes->post('portfolios/edit/(:num)', 'PortfolioController::edit/$1');
    $routes->get('portfolios/delete/(:num)', 'PortfolioController::delete/$1');
    $routes->get('portfolios/view/(:num)', 'PortfolioController::view/$1');

    // Payment Routes
    $routes->get('payments', 'PaymentController::index');
    $routes->get('payments/create', 'PaymentController::create');
    $routes->post('payments/create', 'PaymentController::create');
    $routes->get('payments/create/(:num)', 'PaymentController::create/$1');
    $routes->get('payments/view/(:num)', 'PaymentController::view/$1');
    $routes->get('payments/delete/(:num)', 'PaymentController::delete/$1');
    $routes->get('payments/report', 'PaymentController::report');

    // Webhook Routes
    $routes->get('webhooks', 'WebhookController::index');
    $routes->get('webhooks/create', 'WebhookController::create');
    $routes->post('webhooks/create', 'WebhookController::create');
    $routes->get('webhooks/edit/(:num)', 'WebhookController::edit/$1');
    $routes->post('webhooks/edit/(:num)', 'WebhookController::edit/$1');
    $routes->get('webhooks/delete/(:num)', 'WebhookController::delete/$1');
    $routes->get('webhooks/logs/(:num)', 'WebhookController::logs/$1');
    $routes->get('webhooks/test/(:num)', 'WebhookController::test/$1');
    $routes->get('webhooks/retry/(:num)', 'WebhookController::retry/$1');

    // Removed duplicated logout route that was here
});