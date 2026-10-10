<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\App;
use Config\Auth;
use Config\Database;
use Config\Email;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Libraries\QueuedEmail;
use Geminus\Admin\Libraries\UserProvisioning;
use Geminus\Admin\Models\EmailDeliveryLogModel;
use Geminus\Admin\Models\MicrosoftLinkRequestModel;
use Geminus\Admin\Models\NotificationModel;

class Services extends BaseService
{
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
