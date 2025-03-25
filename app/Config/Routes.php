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
$routes->post('auth/login', 'Auth::attemptLogin');
$routes->get('auth/forgot-password', 'Auth::forgotPassword');
$routes->post('auth/forgot-password', 'Auth::attemptForgotPassword');
$routes->get('auth/reset-password/(:segment)', 'Auth::resetPassword/$1');
$routes->post('auth/reset-password/(:segment)', 'Auth::attemptResetPassword/$1');

// API Routes - Public
$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors'], function ($routes) {
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

    // Invoice Routes
    $routes->match(['get', 'options'], 'invoices', 'InvoiceController::index');
    $routes->match(['post', 'options'], 'invoices', 'InvoiceController::create');
    $routes->match(['get', 'options'], 'invoices/(:num)', 'InvoiceController::show/$1');
    $routes->match(['put', 'options'], 'invoices/(:num)', 'InvoiceController::update/$1');
    $routes->match(['delete', 'options'], 'invoices/(:num)', 'InvoiceController::delete/$1');

    // User Routes
    $routes->match(['get', 'options'], 'users', 'UserController::index');
    $routes->match(['post', 'options'], 'users', 'UserController::create');
    $routes->match(['get', 'options'], 'users/(:num)', 'UserController::show/$1');
    $routes->match(['put', 'options'], 'users/(:num)', 'UserController::update/$1');
    $routes->match(['delete', 'options'], 'users/(:num)', 'UserController::delete/$1');

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
    $routes->get('organizations', 'Organization::index');
    $routes->get('organizations/create', 'Organization::create');
    $routes->post('organizations/create', 'Organization::store');
    $routes->get('organizations/edit/(:num)', 'Organization::edit/$1');
    $routes->post('organizations/edit/(:num)', 'Organization::update/$1');
    $routes->get('organizations/delete/(:num)', 'Organization::delete/$1');

    // Client Routes
    $routes->get('clients', 'Client::index');
    $routes->get('clients/create', 'Client::create');
    $routes->post('clients/create', 'Client::store');
    $routes->get('clients/edit/(:num)', 'Client::edit/$1');
    $routes->post('clients/edit/(:num)', 'Client::update/$1');
    $routes->get('clients/delete/(:num)', 'Client::delete/$1');
    $routes->get('clients/import', 'Client::import');
    $routes->post('clients/import', 'Client::importStore');

    // Invoice Routes
    $routes->get('invoices', 'Invoice::index');
    $routes->get('invoices/create', 'Invoice::create');
    $routes->post('invoices/create', 'Invoice::store');
    $routes->get('invoices/edit/(:num)', 'Invoice::edit/$1');
    $routes->post('invoices/edit/(:num)', 'Invoice::update/$1');
    $routes->get('invoices/delete/(:num)', 'Invoice::delete/$1');
    $routes->get('invoices/import', 'Invoice::import');
    $routes->post('invoices/import', 'Invoice::importStore');

    // User Routes
    $routes->get('users', 'User::index');
    $routes->get('users/create', 'User::create');
    $routes->post('users/create', 'User::store');
    $routes->get('users/edit/(:num)', 'User::edit/$1');
    $routes->post('users/edit/(:num)', 'User::update/$1');
    $routes->get('users/delete/(:num)', 'User::delete/$1');

    // Profile Routes
    $routes->get('profile', 'Profile::index');
    $routes->post('profile/update', 'Profile::update');
    $routes->post('profile/change-password', 'Profile::changePassword');

    // Auth Protected Routes
    $routes->get('auth/logout', 'Auth::logout');
});
