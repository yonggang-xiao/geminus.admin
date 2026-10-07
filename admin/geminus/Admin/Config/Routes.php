<?php

$routes->set404Override(static fn () => view('Geminus\Admin\Views\errors\404'));

$routes->group('admin/files', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('(:segment)/(:segment)', 'FileController::serve/$1/$2', ['as' => 'admin/files/serve']);
});

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
    $routes->get('settings/email', 'EmailSettings::index', ['as' => 'admin/settings/email', 'filter' => 'permission:admin.settings']);
    $routes->post('settings/email', 'EmailSettings::update', ['as' => 'admin/settings/email/update', 'filter' => 'permission:admin.settings']);
    $routes->post('settings/email/test', 'EmailSettings::sendTest', ['as' => 'admin/settings/email/test', 'filter' => 'permission:admin.settings']);
    $routes->get('settings/microsoft', 'MicrosoftSettings::index', ['as' => 'admin/settings/microsoft', 'filter' => 'permission:admin.settings']);
    $routes->post('settings/microsoft', 'MicrosoftSettings::update', ['as' => 'admin/settings/microsoft/update', 'filter' => 'permission:admin.settings']);
    $routes->post('settings/microsoft/requests/(:num)/approve', 'MicrosoftSettings::approve/$1', ['as' => 'admin/settings/microsoft/approve', 'filter' => 'permission:users.manage-admins']);
    $routes->post('settings/microsoft/requests/(:num)/reject', 'MicrosoftSettings::reject/$1', ['as' => 'admin/settings/microsoft/reject', 'filter' => 'permission:users.manage-admins']);
    $routes->post('settings/microsoft/users/(:num)/revoke', 'MicrosoftSettings::revoke/$1', ['as' => 'admin/settings/microsoft/revoke', 'filter' => 'permission:users.manage-admins']);
    $routes->get('profile', 'Profile::index', ['as' => 'admin/profile']);
    $routes->post('profile', 'Profile::update', ['as' => 'admin/profile/update']);
    $routes->post('profile/language', 'Profile::language', ['as' => 'admin/profile/language']);
    $routes->post('profile/avatar', 'Profile::avatar', ['as' => 'admin/profile/avatar']);
    $routes->post('profile/avatar/remove', 'Profile::removeAvatar', ['as' => 'admin/profile/avatar/remove']);
    $routes->post('profile/password', 'Profile::password', ['as' => 'admin/profile/password']);
    $routes->post('profile/microsoft/connect', 'MicrosoftLogin::connect', ['as' => 'admin/profile/microsoft/connect']);
    $routes->post('profile/tokens', 'Profile::createToken', ['as' => 'admin/profile/tokens']);
    $routes->post('profile/tokens/(:num)/revoke', 'Profile::revokeToken/$1', ['as' => 'admin/profile/tokens/revoke']);
});
