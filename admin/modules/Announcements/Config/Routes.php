<?php

$routes->group('{locale}/admin/announcements', ['namespace' => 'Modules\Announcements\Controllers', 'filter' => 'permission:announcements.manage'], static function ($routes) {
    $routes->get('', 'Announcements::index', ['as' => 'admin/announcements']);
    $routes->get('create', 'Announcements::create', ['as' => 'admin/announcements/create']);
    $routes->post('create', 'Announcements::store', ['as' => 'admin/announcements/store']);
});
