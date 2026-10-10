<?php

use CodeIgniter\Events\Events;
use Modules\Announcements\Libraries\AnnouncementNotifications;
use Modules\Announcements\Models\AnnouncementModel;

Events::on('announcements.published', static function (int $announcementId, int $publisherId): void {
    (new AnnouncementNotifications(new AnnouncementModel(), auth()->getProvider(), service('notifications')))->published($announcementId, $publisherId);
});
