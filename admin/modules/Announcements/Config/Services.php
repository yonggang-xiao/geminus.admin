<?php

declare(strict_types=1);

namespace Modules\Announcements\Config;

use CodeIgniter\Config\BaseService;
use Modules\Announcements\Libraries\AnnouncementPublication;
use Modules\Announcements\Models\AnnouncementModel;

class Services extends BaseService
{
    public static function announcementPublication(bool $getShared = false): AnnouncementPublication
    {
        if ($getShared) {
            return static::getSharedInstance('announcementPublication');
        }

        return new AnnouncementPublication(new AnnouncementModel(), service('logger'));
    }
}
