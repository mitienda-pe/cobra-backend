<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// Auth routes
$routes->get('/', 'Home::index');
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::login');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('auth/forgot-password', 'Auth::forgot_password');
$routes->post('auth/forgot-password', 'Auth::forgot_password');
$routes->get('auth/reset-password/(:segment)', 'Auth::reset_password/$1');
$routes->post('auth/reset-password/(:segment)', 'Auth::reset_password/$1');

// Dashboard route
$routes->get('dashboard', 'Dashboard::index');

// User routes
$routes->get('users', 'Users::index');
$routes->get('users/create', 'Users::create');
$routes->post('users/create', 'Users::create');
$routes->get('users/edit/(:num)', 'Users::edit/$1');
$routes->post('users/edit/(:num)', 'Users::edit/$1');
$routes->get('users/delete/(:num)', 'Users::delete/$1');
$routes->get('users/profile', 'Users::profile');
$routes->post('users/profile', 'Users::profile');

// Organization routes
$routes->get('organizations', 'OrganizationsController::index');
$routes->get('organizations/create', 'OrganizationsController::create');
$routes->post('organizations/create', 'OrganizationsController::create');
$routes->get('organizations/edit/(:num)', 'OrganizationsController::edit/$1');
$routes->post('organizations/edit/(:num)', 'OrganizationsController::edit/$1');
$routes->get('organizations/delete/(:num)', 'OrganizationsController::delete/$1');
$routes->get('organizations/view/(:num)', 'OrganizationsController::view/$1');

// Client routes
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

// Debug routes
$routes->get('debug/client-create', 'Debug::clientCreate');
$routes->post('debug/client-create', 'Debug::clientCreate');
$routes->get('debug/auth-info', 'Debug::authInfo');
$routes->get('debug/get-users-by-organization/(:num)', 'Debug::getUsersByOrganization/$1');
$routes->get('debug/get-clients-by-organization/(:num)', 'Debug::getClientsByOrganization/$1');
$routes->get('debug/orgContext', 'Debug::orgContext');
$routes->get('debug/csrf', 'Debug::csrf');

// Portfolio routes
$routes->get('portfolios', 'PortfoliosController::index');
$routes->get('portfolios/create', 'PortfoliosController::create');
$routes->post('portfolios/create', 'PortfoliosController::create');
$routes->get('portfolios/edit/(:num)', 'PortfoliosController::edit/$1');
$routes->post('portfolios/edit/(:num)', 'PortfoliosController::edit/$1');
$routes->get('portfolios/delete/(:num)', 'PortfoliosController::delete/$1');
$routes->get('portfolios/view/(:num)', 'PortfoliosController::view/$1');

// Invoice routes
$routes->get('invoices', 'InvoicesController::index');
$routes->get('invoices/create', 'InvoicesController::create');
$routes->post('invoices/create', 'InvoicesController::create');
$routes->get('invoices/edit/(:num)', 'InvoicesController::edit/$1');
$routes->post('invoices/edit/(:num)', 'InvoicesController::edit/$1');
$routes->get('invoices/delete/(:num)', 'InvoicesController::delete/$1');
$routes->get('invoices/view/(:num)', 'InvoicesController::view/$1');
// Invoice import routes (with CSRF bypass)
$routes->get('invoices/import', 'InvoicesController::import', ['csrf' => false]);
$routes->post('invoices/import', 'InvoicesController::import', ['csrf' => false]);

// Payment routes
$routes->get('payments', 'PaymentsController::index');
$routes->get('payments/create', 'PaymentsController::create');
$routes->get('payments/create/(:num)', 'PaymentsController::create/$1');
$routes->post('payments/create', 'PaymentsController::create');
$routes->get('payments/view/(:num)', 'PaymentsController::view/$1');
$routes->get('payments/delete/(:num)', 'PaymentsController::delete/$1');
$routes->get('payments/report', 'PaymentsController::report');

// Webhook routes
$routes->get('webhooks', 'WebhooksController::index');
$routes->get('webhooks/create', 'WebhooksController::create');
$routes->post('webhooks/create', 'WebhooksController::create');
$routes->get('webhooks/edit/(:num)', 'WebhooksController::edit/$1');
$routes->post('webhooks/edit/(:num)', 'WebhooksController::edit/$1');
$routes->get('webhooks/delete/(:num)', 'WebhooksController::delete/$1');
$routes->get('webhooks/logs/(:num)', 'WebhooksController::logs/$1');
$routes->get('webhooks/test/(:num)', 'WebhooksController::test/$1');
$routes->get('webhooks/retry/(:num)', 'WebhooksController::retry/$1');

// API Routes
$routes->group('api', function($routes) {
    // Auth API Routes
    $routes->post('auth/request-otp', 'Api\AuthController::requestOtp');
    $routes->post('auth/verify-otp', 'Api\AuthController::verifyOtp');
    $routes->post('auth/refresh-token', 'Api\AuthController::refreshToken');
    $routes->post('auth/logout', 'Api\AuthController::logout');
    
    // User API Routes
    $routes->get('user/profile', 'Api\UserController::profile');
    $routes->get('users', 'Api\UserController::index');
    $routes->get('users/portfolio/(:num)', 'Api\UserController::byPortfolio/$1');
    
    // Client API Routes
    $routes->get('clients', 'Api\ClientController::index');
    $routes->get('clients/(:num)', 'Api\ClientController::show/$1');
    $routes->get('clients/external/(:segment)', 'Api\ClientController::findByExternalId/$1');
    $routes->get('clients/document/(:segment)', 'Api\ClientController::findByDocument/$1');
    $routes->get('clients/uuid/(:segment)', 'Api\ClientController::findByUuid/$1');
    
    // Portfolio API Routes
    $routes->get('portfolios', 'Api\PortfolioController::index');
    $routes->get('portfolios/(:num)', 'Api\PortfolioController::show/$1');
    $routes->get('portfolios/my', 'Api\PortfolioController::myPortfolios');
    
    // Invoice API Routes
    $routes->get('invoices', 'Api\InvoiceController::index');
    $routes->get('invoices/(:num)', 'Api\InvoiceController::show/$1');
    $routes->post('invoices', 'Api\InvoiceController::create');
    $routes->put('invoices/(:num)', 'Api\InvoiceController::update/$1');
    $routes->delete('invoices/(:num)', 'Api\InvoiceController::delete/$1');
    $routes->get('invoices/external/(:segment)', 'Api\InvoiceController::findByExternalId/$1');
    $routes->get('invoices/overdue', 'Api\InvoiceController::overdue');
    
    // Payment API Routes
    $routes->get('payments', 'Api\PaymentController::index');
    $routes->get('payments/(:num)', 'Api\PaymentController::show/$1');
    $routes->post('payments', 'Api\PaymentController::create');
    $routes->put('payments/(:num)', 'Api\PaymentController::update/$1');
    $routes->delete('payments/(:num)', 'Api\PaymentController::delete/$1');
    $routes->get('payments/external/(:segment)', 'Api\PaymentController::findByExternalId/$1');
});
