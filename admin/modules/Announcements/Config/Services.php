<?php

declare(strict_types=1);

namespace Modules\Announcements\Config;

use CodeIgniter\Config\BaseService;
use Modules\Announcements\Libraries\AnnouncementDashboardProvider;
use Modules\Announcements\Libraries\AnnouncementPublication;
use Modules\Announcements\Libraries\AnnouncementUploadSource;
use Modules\Announcements\Models\AnnouncementModel;

class Services extends BaseService
{
    public static function announcementDashboardProvider(bool $getShared = false): AnnouncementDashboardProvider
    {
        if ($getShared) {
            return static::getSharedInstance('announcementdashboardprovider');
        }

        return new AnnouncementDashboardProvider(new AnnouncementModel());
    }

    public static function announcementUploadSource(bool $getShared = false): AnnouncementUploadSource
    {
        if ($getShared) {
            return static::getSharedInstance('announcementuploadsource');
        }

        return new AnnouncementUploadSource(new AnnouncementModel());
    }

    public static function announcementPublication(bool $getShared = false): AnnouncementPublication
    {
        if ($getShared) {
            return static::getSharedInstance('announcementPublication');
        }

        return new AnnouncementPublication(new AnnouncementModel(), service('logger'));
    }
}
