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
$routes->get('organizations', 'Organizations::index');
$routes->get('organizations/create', 'Organizations::create');
$routes->post('organizations/create', 'Organizations::create');
$routes->get('organizations/edit/(:num)', 'Organizations::edit/$1');
$routes->post('organizations/edit/(:num)', 'Organizations::edit/$1');
$routes->get('organizations/delete/(:num)', 'Organizations::delete/$1');
$routes->get('organizations/view/(:num)', 'Organizations::view/$1');
