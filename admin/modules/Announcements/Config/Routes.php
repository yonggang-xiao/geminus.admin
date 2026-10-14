<?php

$routes->resource('api/v1/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'controller' => 'ApiAnnouncements', 'only' => ['create'], 'filter' => 'api:announcements.manage']);

$routes->group('{locale}/admin', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.access,announcements.manage'], static function ($routes) {
    $routes->presenter('announcements', ['controller' => 'Announcements', 'only' => ['index'], 'placeholder' => '(:num)', 'as' => 'admin/announcements']);
    $routes->presenter('announcements', ['controller' => 'Announcements', 'only' => ['show'], 'placeholder' => '(:num)', 'as' => 'admin/announcements/show']);
    $routes->presenter('announcements', ['controller' => 'Announcements', 'only' => ['show'], 'placeholder' => '(:num)']);
});

$routes->group('{locale}/admin/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.access,announcements.manage'], static function ($routes) {
    $routes->get('(:num)/attachments/(:num)', 'AnnouncementAttachments::download/$1/$2', ['as' => 'admin/announcements/attachments/download']);
    $routes->get('(:num)/attachments/(:num)/preview', 'AnnouncementAttachments::preview/$1/$2', ['as' => 'admin/announcements/attachments/preview']);
});

$routes->group('{locale}/admin', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.manage'], static function ($routes) {
    foreach (['new', 'create', 'edit', 'update', 'delete'] as $action) {
        $routes->presenter('announcements', ['controller' => 'Announcements', 'only' => [$action], 'placeholder' => '(:num)', 'as' => 'admin/announcements/' . $action]);
    }
});

$routes->group('{locale}/admin/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.manage'], static function ($routes) {
    $routes->post('(:num)/publish', 'Announcements::publish/$1', ['as' => 'admin/announcements/publish']);
    $routes->get('import', 'Announcements::importForm', ['as' => 'admin/announcements/import']);
    $routes->post('import', 'Announcements::import', ['as' => 'admin/announcements/import/store']);
    $routes->get('template', 'Announcements::template', ['as' => 'admin/announcements/template']);
    $routes->get('export', 'Announcements::export', ['as' => 'admin/announcements/export']);
    $routes->get('(:num)/attachments', 'AnnouncementAttachments::index/$1', ['as' => 'admin/announcements/attachments']);
    $routes->post('(:num)/attachments', 'AnnouncementAttachments::upload/$1', ['as' => 'admin/announcements/attachments/upload']);
    $routes->post('(:num)/attachments/(:num)/remove', 'AnnouncementAttachments::remove/$1/$2', ['as' => 'admin/announcements/attachments/remove']);
});
