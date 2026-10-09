<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;
use Geminus\Admin\Libraries\SuperadminGrants;

class RegisterAdminFeaturePermissions extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        $matrix      = setting('AuthGroups.matrix');

        $featurePermissions = [
            'users.view'                => 'View users, export user records and read user attachments',
            'email-settings.manage'     => 'Manage email delivery settings and send test emails',
            'email-deliveries.view'     => 'View email delivery records',
            'email-templates.manage'    => 'View, edit and reset email templates',
            'operation-audit.view'      => 'View operation audit records',
            'microsoft-settings.manage' => 'View and configure Microsoft login settings',
        ];

        foreach ($featurePermissions as $permission => $description) {
            $permissions[$permission] ??= $description;
        }
        $matrix['superadmin'] = SuperadminGrants::withPermissions($matrix['superadmin'] ?? [], array_keys($featurePermissions));

        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
    }

    public function down(): void
    {
    }
}
