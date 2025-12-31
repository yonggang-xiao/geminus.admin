<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('{locale}', static function($routes) {
    service('auth')->routes($routes);
});
