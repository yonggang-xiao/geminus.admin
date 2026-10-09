<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Migrations;

use CodeIgniter\Database\Migration;
use Geminus\Admin\Libraries\SuperadminGrants;

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
        $matrix               = setting('AuthGroups.matrix');
        $matrix['superadmin'] = SuperadminGrants::withPermissions($matrix['superadmin'] ?? [], ['announcements.access', 'announcements.manage']);
        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
    }

    public function down(): void
    {
    }
}
