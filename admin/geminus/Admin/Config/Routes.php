<?php

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
});
