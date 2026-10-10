<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Shield\Models\UserModel;
use Geminus\Admin\Libraries\Notifications;
use Modules\Announcements\Models\AnnouncementModel;

class AnnouncementNotifications
{
    public function __construct(private readonly AnnouncementModel $announcements, private readonly UserModel $users, private readonly Notifications $notifications)
    {
    }

    public function published(int $announcementId, int $publisherId): void
    {
        $announcement = $this->announcements->where('status', 'published')->find($announcementId);
        if ($announcement === null) {
            return;
        }
        $permissions = ['announcements.access', 'announcements.manage'];
        $offset      = 0;

        do {
            $users = $this->users->orderBy('id')->findAll(100, $offset);

            foreach ($users as $user) {
                if ((int) $user->id !== $publisherId && $user->can(...$permissions)) {
                    $this->notifications->send((int) $user->id, 'announcements.published', $announcementId, $announcement['title'], 'admin/announcements/show', $permissions, [$announcementId]);
                }
            }
            $offset += 100;
        } while (count($users) === 100);
    }
}
