<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Authorization\PermissionMatcher;

class RegisterAdminFeaturePermissions extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        $matrix      = setting('AuthGroups.matrix');

        foreach ([
            'email-settings.manage'     => 'Manage email delivery settings and send test emails',
            'email-deliveries.view'     => 'View email delivery records',
            'email-templates.manage'    => 'View, edit and reset email templates',
            'operation-audit.view'      => 'View operation audit records',
            'microsoft-settings.manage' => 'View and configure Microsoft login settings',
        ] as $permission => $description) {
            $permissions[$permission] ??= $description;
            if (! PermissionMatcher::matches($permission, $matrix['superadmin'] ?? [])) {
                $matrix['superadmin'][] = $permission;
            }
        }

        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
    }

    public function down(): void
    {
    }
}
