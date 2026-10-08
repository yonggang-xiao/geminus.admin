<?php

$routes->set404Override(static fn () => view('Geminus\Admin\Views\errors\404'));

$routes->group('admin/files', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('(:segment)/(:segment)', 'FileController::serve/$1/$2', ['as' => 'admin/files/serve']);
});

$routes->group('{locale}/admin', ['namespace' => 'Geminus\Admin\Controllers'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'admin/dashboard']);
    $routes->group('users', ['filter' => 'permission:users.manage-admins'], static function ($routes) {
        $routes->get('', 'Users::index', ['as' => 'admin/users']);
        $routes->get('create', 'Users::create', ['as' => 'admin/users/create']);
        $routes->post('create', 'Users::store', ['as' => 'admin/users/store']);
        $routes->get('(:num)/edit', 'Users::edit/$1', ['as' => 'admin/users/edit']);
        $routes->post('(:num)/edit', 'Users::update/$1', ['as' => 'admin/users/update']);
        $routes->post('(:num)/invite', 'Users::invite/$1', ['as' => 'admin/users/invite']);
        $routes->get('template', 'Users::template', ['as' => 'admin/users/template']);
        $routes->get('export', 'Users::export', ['as' => 'admin/users/export']);
        $routes->post('import', 'Users::import', ['as' => 'admin/users/import']);
    });
    $routes->group('settings/email', ['filter' => 'permission:admin.settings'], static function ($routes) {
        $routes->get('', 'EmailSettings::index', ['as' => 'admin/settings/email']);
        $routes->post('', 'EmailSettings::update', ['as' => 'admin/settings/email/update']);
        $routes->post('test', 'EmailSettings::sendTest', ['as' => 'admin/settings/email/test']);
    });
    $routes->group('mail', ['filter' => 'permission:admin.settings'], static function ($routes) {
        $routes->get('deliveries', 'EmailQueue::index', ['as' => 'admin/mail/deliveries']);
        $routes->get('templates', 'EmailTemplates::index', ['as' => 'admin/mail/templates']);
        $routes->post('templates/(:segment)/(:segment)', 'EmailTemplates::update/$1/$2', ['as' => 'admin/mail/templates/update']);
        $routes->post('templates/(:segment)/(:segment)/reset', 'EmailTemplates::reset/$1/$2', ['as' => 'admin/mail/templates/reset']);
    });
    $routes->group('settings', ['filter' => 'group:superadmin'], static function ($routes) {
        $routes->get('roles', 'RoleSettings::index', ['as' => 'admin/settings/roles']);
        $routes->post('roles', 'RoleSettings::createRole', ['as' => 'admin/settings/roles/create']);
        $routes->post('roles/(:segment)', 'RoleSettings::updateRole/$1', ['as' => 'admin/settings/roles/update']);
        $routes->post('roles/(:segment)/permissions', 'RoleSettings::permissions/$1', ['as' => 'admin/settings/roles/permissions']);
        $routes->post('permissions', 'RoleSettings::createPermission', ['as' => 'admin/settings/permissions/create']);
        $routes->post('permissions/(:segment)', 'RoleSettings::updatePermission/$1', ['as' => 'admin/settings/permissions/update']);
    });
    $routes->group('settings/microsoft', ['filter' => 'permission:admin.settings'], static function ($routes) {
        $routes->get('', 'MicrosoftSettings::index', ['as' => 'admin/settings/microsoft']);
        $routes->post('', 'MicrosoftSettings::update', ['as' => 'admin/settings/microsoft/update']);
    });
    $routes->post('settings/microsoft/requests/(:num)/approve', 'MicrosoftSettings::approve/$1', ['as' => 'admin/settings/microsoft/approve', 'filter' => 'permission:users.manage-admins']);
    $routes->post('settings/microsoft/requests/(:num)/reject', 'MicrosoftSettings::reject/$1', ['as' => 'admin/settings/microsoft/reject', 'filter' => 'permission:users.manage-admins']);
    $routes->post('settings/microsoft/users/(:num)/revoke', 'MicrosoftSettings::revoke/$1', ['as' => 'admin/settings/microsoft/revoke', 'filter' => 'permission:users.manage-admins']);
    $routes->group('profile', static function ($routes) {
        $routes->get('', 'Profile::index', ['as' => 'admin/profile']);
        $routes->post('', 'Profile::update', ['as' => 'admin/profile/update']);
        $routes->post('language', 'Profile::language', ['as' => 'admin/profile/language']);
        $routes->post('avatar', 'Profile::avatar', ['as' => 'admin/profile/avatar']);
        $routes->post('avatar/remove', 'Profile::removeAvatar', ['as' => 'admin/profile/avatar/remove']);
        $routes->post('password', 'Profile::password', ['as' => 'admin/profile/password']);
        $routes->post('microsoft/connect', 'MicrosoftLogin::connect', ['as' => 'admin/profile/microsoft/connect']);
        $routes->post('tokens', 'Profile::createToken', ['as' => 'admin/profile/tokens']);
        $routes->post('tokens/(:num)/revoke', 'Profile::revokeToken/$1', ['as' => 'admin/profile/tokens/revoke']);
    });
});
