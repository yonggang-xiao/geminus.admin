<?php

$routes->group('{locale}/admin/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.access,announcements.manage'], static function ($routes) {
    $routes->get('', 'Announcements::index', ['as' => 'admin/announcements']);
    $routes->get('(:num)', 'Announcements::show/$1', ['as' => 'admin/announcements/show']);
    $routes->get('(:num)/attachments/(:num)', 'AnnouncementAttachments::download/$1/$2', ['as' => 'admin/announcements/attachments/download']);
    $routes->get('(:num)/attachments/(:num)/preview', 'AnnouncementAttachments::preview/$1/$2', ['as' => 'admin/announcements/attachments/preview']);
});

$routes->group('{locale}/admin/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.manage'], static function ($routes) {
    $routes->get('create', 'Announcements::create', ['as' => 'admin/announcements/create']);
    $routes->post('create', 'Announcements::store', ['as' => 'admin/announcements/store']);
    $routes->get('(:num)/edit', 'Announcements::edit/$1', ['as' => 'admin/announcements/edit']);
    $routes->post('(:num)/edit', 'Announcements::update/$1', ['as' => 'admin/announcements/update']);
    $routes->post('(:num)/publish', 'Announcements::publish/$1', ['as' => 'admin/announcements/publish']);
    $routes->get('import', 'Announcements::importForm', ['as' => 'admin/announcements/import']);
    $routes->post('import', 'Announcements::import', ['as' => 'admin/announcements/import/store']);
    $routes->get('template', 'Announcements::template', ['as' => 'admin/announcements/template']);
    $routes->get('export', 'Announcements::export', ['as' => 'admin/announcements/export']);
    $routes->get('(:num)/attachments', 'AnnouncementAttachments::index/$1', ['as' => 'admin/announcements/attachments']);
    $routes->post('(:num)/attachments', 'AnnouncementAttachments::upload/$1', ['as' => 'admin/announcements/attachments/upload']);
    $routes->post('(:num)/attachments/(:num)/remove', 'AnnouncementAttachments::remove/$1/$2', ['as' => 'admin/announcements/attachments/remove']);
});
