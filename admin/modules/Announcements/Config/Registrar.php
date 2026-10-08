<?php

declare(strict_types=1);

namespace Modules\Announcements\Config;

class Registrar
{
    public static function AdminMenu(): array
    {
        return ['items' => [[
            'permission' => 'announcements.manage',
            'route'      => 'admin/announcements',
            'label'      => 'Announcements.title',
            'icon'       => 'ti-speakerphone',
            'active'     => '*/admin/announcements*',
        ]]];
    }
}
