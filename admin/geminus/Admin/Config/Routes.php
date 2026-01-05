<?php

$routes->set404Override(static fn () => view('Geminus\Admin\Views\errors\404'));

$routes->group('admin/files', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('(:segment)/(:segment)', 'FileController::serve/$1/$2', ['as' => 'admin/files/serve']);
});

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
});
