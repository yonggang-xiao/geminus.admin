<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Authorization\PermissionMatcher;

class GrantAnnouncementsToSuperadmin extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        if (! isset($permissions['announcements.manage'])) {
            $permissions['announcements.manage'] = 'Can manage example announcements';
            setting('AuthGroups.permissions', $permissions);
        }

        $matrix = setting('AuthGroups.matrix');
        $grants = $matrix['superadmin'] ?? [];
        if (PermissionMatcher::matches('announcements.manage', $grants)) {
            return;
        }

        $matrix['superadmin'][] = 'announcements.manage';
        setting('AuthGroups.matrix', $matrix);
    }

    public function down(): void
    {
    }
}
