<?php

declare(strict_types=1);

namespace Modules\Announcements\Config;

class Registrar
{
    public static function OperationAudit(): array
    {
        return ['operations' => [
            [
                'controller' => 'Modules\\Announcements\\Controllers\\Announcements::store',
                'methods'    => ['POST'], 'action' => 'announcement.create',
                'object'     => ['type' => 'announcement'], 'fields' => ['title'],
            ],
            [
                'controller' => 'Modules\\Announcements\\Controllers\\Announcements::update',
                'methods'    => ['POST'], 'action' => 'announcement.update',
                'object'     => ['type' => 'announcement', 'route_parameter' => 0], 'fields' => ['title'],
            ],
            [
                'controller' => 'Modules\\Announcements\\Controllers\\AnnouncementAttachments::upload',
                'methods'    => ['POST'], 'action' => 'announcement.attachment.upload',
                'object'     => ['type' => 'announcements', 'route_parameter' => 0], 'fields' => ['file.name', 'file.size', 'file.type'],
            ],
        ]];
    }

    public static function Dashboard(): array
    {
        return ['providers' => [
            'announcements' => [
                'service'     => 'announcementdashboardprovider', 'label' => 'Announcements.title',
                'permissions' => ['announcements.access', 'announcements.manage'], 'order' => 100,
            ],
        ]];
    }

    public static function UploadHistory(): array
    {
        return ['sources' => ['announcement' => 'announcementuploadsource']];
    }

    public static function AdminMenu(): array
    {
        return ['items' => [[
            'permission' => ['announcements.access', 'announcements.manage'],
            'route'      => 'admin/announcements',
            'label'      => 'Announcements.title',
            'icon'       => 'ti-speakerphone',
            'active'     => '*/admin/announcements*',
        ]]];
    }
}
