<?php

declare(strict_types=1);

namespace Modules\Announcements\Config;

class Registrar
{
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
