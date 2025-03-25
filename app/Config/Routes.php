<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// API Routes - Make sure these are defined before other routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function($routes) {
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

// Default route to login page
$routes->get('/', 'Home::index');

// Auth routes
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::login');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('auth/forgot-password', 'Auth::forgot_password');
$routes->post('auth/forgot-password', 'Auth::forgot_password');
$routes->get('auth/reset-password/(:segment)', 'Auth::reset_password/$1');
$routes->post('auth/reset-password/(:segment)', 'Auth::reset_password/$1');
