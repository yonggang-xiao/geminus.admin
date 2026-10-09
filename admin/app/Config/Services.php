<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Email\Email as EmailService;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\QueuedEmail;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function mailTemplates(bool $getShared = true): MailTemplates
    {
        if ($getShared) {
            return static::getSharedInstance('mailTemplates');
        }

        return new MailTemplates(service('settings'), service('language'), config(App::class)->supportedLocales);
    }

    public static function email($config = null, bool $getShared = true): EmailService
    {
        if ($getShared) {
            return static::getSharedInstance('email', $config);
        }

        $config ??= clone config(Email::class);

        if ($config instanceof Email) {
            foreach (['fromEmail', 'fromName', 'protocol', 'SMTPHost', 'SMTPUser', 'SMTPPort', 'SMTPCrypto'] as $property) {
                $config->{$property} = service('settings')->get('Email.' . $property);
            }
        }

        return new QueuedEmail($config);
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
