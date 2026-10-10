<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

class Registrar
{
    public static function Dashboard(): array
    {
        return ['providers' => [
            'users' => ['service' => 'userdashboardprovider', 'label' => 'Admin.users', 'permissions' => ['users.view', 'users.create'], 'order' => 10],
            'email' => ['service' => 'emaildashboardprovider', 'label' => 'Admin.mailDeliveries', 'permissions' => ['email-deliveries.view'], 'order' => 20],
            'audit' => ['service' => 'auditdashboardprovider', 'label' => 'Admin.operationAudit', 'permissions' => ['operation-audit.view'], 'order' => 30],
        ]];
    }
}
