<?php

$routes->set404Override(static fn () => view('Geminus\Admin\Views\errors\404'));

$routes->group('admin/files', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('(:segment)/(:segment)', 'FileController::serve/$1/$2', ['as' => 'admin/files/serve']);
});

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
    $routes->get('profile', 'Profile::index', ['as' => 'admin/profile']);
    $routes->post('profile', 'Profile::update', ['as' => 'admin/profile/update']);
    $routes->post('profile/avatar', 'Profile::avatar', ['as' => 'admin/profile/avatar']);
    $routes->post('profile/avatar/remove', 'Profile::removeAvatar', ['as' => 'admin/profile/avatar/remove']);
    $routes->post('profile/password', 'Profile::password', ['as' => 'admin/profile/password']);
    $routes->post('profile/tokens', 'Profile::createToken', ['as' => 'admin/profile/tokens']);
    $routes->post('profile/tokens/(:num)/revoke', 'Profile::revokeToken/$1', ['as' => 'admin/profile/tokens/revoke']);
});
