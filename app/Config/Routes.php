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

// Do not auto-route directories
$routes->setAutoRoute(false);

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Auth API Routes
    $routes->match(['post', 'options'], 'auth/request-otp', 'AuthController::requestOtp');
    $routes->match(['post', 'options'], 'auth/verify-otp', 'AuthController::verifyOtp');
    $routes->match(['post', 'options'], 'auth/refresh-token', 'AuthController::refreshToken');
    $routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');
    
    // User API Routes
    $routes->get('user/profile', 'UserController::profile');
    $routes->get('users', 'UserController::index');
    $routes->get('users/portfolio/(:num)', 'UserController::byPortfolio/$1');
    
    // Client API Routes
    $routes->get('clients', 'ClientController::index');
    $routes->get('clients/(:num)', 'ClientController::show/$1');
    $routes->get('clients/external/(:segment)', 'ClientController::findByExternalId/$1');
    $routes->get('clients/document/(:segment)', 'ClientController::findByDocument/$1');
    $routes->get('clients/uuid/(:segment)', 'ClientController::findByUuid/$1');
    
    // Portfolio API Routes
    $routes->get('portfolios', 'PortfolioController::index');
    $routes->get('portfolios/(:num)', 'PortfolioController::show/$1');
    $routes->get('portfolios/my', 'PortfolioController::myPortfolios');
    
    // Invoice API Routes
    $routes->get('invoices', 'InvoiceController::index');
    $routes->get('invoices/(:num)', 'InvoiceController::show/$1');
    $routes->post('invoices', 'InvoiceController::create');
    $routes->put('invoices/(:num)', 'InvoiceController::update/$1');
    $routes->delete('invoices/(:num)', 'InvoiceController::delete/$1');
    $routes->get('invoices/external/(:segment)', 'InvoiceController::findByExternalId/$1');
    $routes->get('invoices/overdue', 'InvoiceController::overdue');
    
    // Payment API Routes
    $routes->get('payments', 'PaymentController::index');
    $routes->get('payments/(:num)', 'PaymentController::show/$1');
    $routes->post('payments', 'PaymentController::create');
    $routes->put('payments/(:num)', 'PaymentController::update/$1');
    $routes->delete('payments/(:num)', 'PaymentController::delete/$1');
    $routes->get('payments/external/(:segment)', 'PaymentController::findByExternalId/$1');
});

// Web Routes
$routes->get('/', 'Home::index');

// Auth routes
$routes->get('auth/login', 'AuthController::login');
$routes->post('auth/login', 'AuthController::attemptLogin');
$routes->get('auth/logout', 'AuthController::logout');

// Protected web routes
$routes->group('', ['filter' => 'auth'], function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    
    // Users
    $routes->get('users', 'UserController::index');
    $routes->get('users/create', 'UserController::create');
    $routes->post('users/create', 'UserController::store');
    $routes->get('users/edit/(:num)', 'UserController::edit/$1');
    $routes->post('users/edit/(:num)', 'UserController::update/$1');
    $routes->get('users/delete/(:num)', 'UserController::delete/$1');

    // Organizations
    $routes->get('organizations', 'OrganizationController::index');
    $routes->get('organizations/create', 'OrganizationController::create');
    $routes->post('organizations/create', 'OrganizationController::store');
    $routes->get('organizations/edit/(:num)', 'OrganizationController::edit/$1');
    $routes->post('organizations/edit/(:num)', 'OrganizationController::update/$1');
    $routes->get('organizations/delete/(:num)', 'OrganizationController::delete/$1');
});
