<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', '\Geminus\Admin\Controllers\Dashboard::index');

$routes->group('{locale}', static function ($routes) {
    service('auth')->routes($routes);
    $routes->get('microsoft/start', '\Geminus\Admin\Controllers\MicrosoftLogin::start', ['as' => 'microsoft/start']);
    $routes->get('microsoft/callback', '\Geminus\Admin\Controllers\MicrosoftLogin::callback', ['as' => 'microsoft/callback']);
});
