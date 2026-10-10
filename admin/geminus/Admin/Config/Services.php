<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\App;
use Config\Auth;
use Config\Database;
use Config\Email;
use Geminus\Admin\Libraries\Dashboard\AuditDashboardProvider;
use Geminus\Admin\Libraries\Dashboard\Dashboard as DashboardLibrary;
use Geminus\Admin\Libraries\Dashboard\DashboardItems;
use Geminus\Admin\Libraries\Dashboard\DashboardLinks;
use Geminus\Admin\Libraries\Dashboard\EmailDashboardProvider;
use Geminus\Admin\Libraries\Dashboard\UserDashboardProvider;
use Geminus\Admin\Libraries\DataManagement\UploadHistory as UploadHistoryLibrary;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Libraries\QueuedEmail;
use Geminus\Admin\Libraries\UserProvisioning;
use Geminus\Admin\Models\AttachmentModel;
use Geminus\Admin\Models\EmailDeliveryLogModel;
use Geminus\Admin\Models\MicrosoftLinkRequestModel;
use Geminus\Admin\Models\NotificationModel;
use Geminus\Admin\Models\OperationAuditModel;
use Geminus\Admin\Models\UserModel;

class Services extends BaseService
{
    public static function userDashboardProvider(bool $getShared = false): UserDashboardProvider
    {
        if ($getShared) {
            return static::getSharedInstance('userdashboardprovider');
        }

        return new UserDashboardProvider(new UserModel());
    }

    public static function emailDashboardProvider(bool $getShared = false): EmailDashboardProvider
    {
        if ($getShared) {
            return static::getSharedInstance('emaildashboardprovider');
        }

        return new EmailDashboardProvider(new EmailDeliveryLogModel());
    }

    public static function auditDashboardProvider(bool $getShared = false): AuditDashboardProvider
    {
        if ($getShared) {
            return static::getSharedInstance('auditdashboardprovider');
        }

        return new AuditDashboardProvider(new OperationAuditModel());
    }

    public static function dashboard(bool $getShared = false): DashboardLibrary
    {
        if ($getShared) {
            return static::getSharedInstance('dashboard');
        }

        $registrations = config(Dashboard::class)->providers;
        DashboardLibrary::validateRegistrations($registrations);
        $providers = [];

        foreach ($registrations as $identifier => $registration) {
            $providers[$identifier] = service($registration['service'], false);
        }

        return new DashboardLibrary(
            $registrations,
            $providers,
            new DashboardItems(new DashboardLinks(service('routes'))),
            service('logger'),
        );
    }

    public static function uploadHistory(bool $getShared = false): UploadHistoryLibrary
    {
        if ($getShared) {
            return static::getSharedInstance('uploadhistory');
        }

        $sources = [];

        foreach (config(UploadHistory::class)->sources as $type => $serviceName) {
            $sources[$type] = service($serviceName);
        }

        return new UploadHistoryLibrary(new AttachmentModel(), $sources);
    }

    public static function notifications(bool $getShared = false): Notifications
    {
        if ($getShared) {
            return static::getSharedInstance('notifications');
        }

        return new Notifications(new NotificationModel());
    }

    public static function userProvisioning(bool $getShared = false): UserProvisioning
    {
        if ($getShared) {
            return static::getSharedInstance('userProvisioning');
        }

        $provider = auth()->getProvider()::class;
        $db       = Database::connect();
        $auth     = config(Auth::class);

        return new UserProvisioning(
            static fn () => new $provider($db),
            new UserIdentityModel($db),
            static::validation(null, false),
            service('passwords'),
            $auth->usernameValidationRules,
            $auth->emailValidationRules,
            array_keys(service('settings')->get('AuthGroups.groups')),
        );
    }

    public static function microsoftLinks(bool $getShared = false): MicrosoftLinks
    {
        if ($getShared) {
            return static::getSharedInstance('microsoftLinks');
        }

        $db = Database::connect();

        return new MicrosoftLinks(new MicrosoftLinkRequestModel($db), new UserIdentityModel($db), auth()->getProvider(), $db);
    }

    public static function mailTemplates(bool $getShared = true): MailTemplates
    {
        if ($getShared) {
            return static::getSharedInstance('mailTemplates');
        }

        return new MailTemplates(service('settings'), service('language'), config(App::class)->supportedLocales);
    }

    public static function queuedEmail($config = null, bool $getShared = false): QueuedEmail
    {
        if ($getShared) {
            return static::getSharedInstance('queuedEmail', $config);
        }

        $config ??= clone config(Email::class);

        if ($config instanceof Email) {
            foreach (['fromEmail', 'fromName', 'protocol', 'SMTPHost', 'SMTPUser', 'SMTPPort', 'SMTPCrypto'] as $property) {
                $config->{$property} = service('settings')->get('Email.' . $property);
            }
        }

        return new QueuedEmail(new EmailDeliveryLogModel(), service('queue'), $config);
    }
}
