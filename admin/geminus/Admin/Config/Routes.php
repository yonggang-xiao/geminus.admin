<?php

$routes->set404Override(static function () {
    return view('Geminus\Admin\Views\errors\404');
});

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
});
