<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Migrations;

use CodeIgniter\Database\Migration;

class GrantAnnouncementsToSuperadmin extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        if (! isset($permissions['announcements.access'])) {
            $permissions['announcements.access'] = 'Can read published announcements';
        }
        if (! isset($permissions['announcements.manage'])) {
            $permissions['announcements.manage'] = 'Can manage example announcements';
        }
        setting('AuthGroups.permissions', $permissions);
    }

    public function down(): void
    {
    }
}
