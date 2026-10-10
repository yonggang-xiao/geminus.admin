<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

class Registrar
{
    public static function OperationAudit(): array
    {
        return ['operations' => [
            [
                'controller' => 'Geminus\\Admin\\Controllers\\Profile::language',
                'methods'    => ['POST'], 'action' => 'profile.language',
                'object'     => ['type' => 'profile'], 'fields' => ['language'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\Profile::update',
                'methods'    => ['POST'], 'action' => 'profile.update',
                'object'     => ['type' => 'profile'], 'fields' => ['language', 'timezone'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\EmailSettings::update',
                'methods'    => ['POST'], 'action' => 'email.settings.update',
                'object'     => ['type' => 'email_settings'], 'fields' => ['protocol', 'SMTPPort', 'SMTPCrypto'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\Users::update',
                'methods'    => ['POST'], 'action' => 'user.update',
                'object'     => ['type' => 'users', 'route_parameter' => 0], 'fields' => ['role', 'status'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\RoleSettings::createRole',
                'methods'    => ['POST'], 'action' => 'role.create',
                'object'     => ['type' => 'roles'], 'fields' => ['name', 'title', 'description'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\RoleSettings::updateRole',
                'methods'    => ['POST'], 'action' => 'role.update',
                'object'     => ['type' => 'roles', 'route_parameter' => 0], 'fields' => ['title', 'description'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\RoleSettings::createPermission',
                'methods'    => ['POST'], 'action' => 'permission.create',
                'object'     => ['type' => 'permissions'], 'fields' => ['name', 'description'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\RoleSettings::updatePermission',
                'methods'    => ['POST'], 'action' => 'permission.update',
                'object'     => ['type' => 'permissions', 'route_parameter' => 0], 'fields' => ['description'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\EmailTemplates::update',
                'methods'    => ['POST'], 'action' => 'email.template.update',
                'object'     => ['type' => 'templates', 'route_parameter' => 0], 'fields' => ['subject'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\MicrosoftSettings::update',
                'methods'    => ['POST'], 'action' => 'microsoft.settings.update',
                'object'     => ['type' => 'microsoft_settings'], 'fields' => ['enabled'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\MicrosoftSettings::approve',
                'methods'    => ['POST'], 'action' => 'microsoft.link.approve',
                'object'     => ['type' => 'requests', 'route_parameter' => 0], 'fields' => ['user_id'],
            ],
            [
                'controller' => 'Geminus\\Admin\\Controllers\\Profile::createToken',
                'methods'    => ['POST'], 'action' => 'profile.token.create',
                'object'     => ['type' => 'tokens'], 'fields' => ['name', 'expires'],
            ],
        ]];
    }

    public static function Dashboard(): array
    {
        return ['providers' => [
            'users' => ['service' => 'userdashboardprovider', 'label' => 'Admin.users', 'permissions' => ['users.view', 'users.create'], 'order' => 10],
            'email' => ['service' => 'emaildashboardprovider', 'label' => 'Admin.mailDeliveries', 'permissions' => ['email-deliveries.view'], 'order' => 20],
            'audit' => ['service' => 'auditdashboardprovider', 'label' => 'Admin.operationAudit', 'permissions' => ['operation-audit.view'], 'order' => 30],
        ]];
    }
}
