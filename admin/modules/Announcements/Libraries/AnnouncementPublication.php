<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Events\Events;
use Modules\Announcements\Models\AnnouncementModel;
use Psr\Log\LoggerInterface;
use Throwable;

class AnnouncementPublication
{
    public function __construct(private readonly AnnouncementModel $model, private readonly LoggerInterface $logger)
    {
    }

    public function publish(int $announcementId, int $publisherId): bool
    {
        if (! $this->model->publishDraft($announcementId)) {
            return false;
        }

        $this->notifyPublished($announcementId, $publisherId);

        return true;
    }

    public function notifyPublished(int $announcementId, int $publisherId): void
    {
        try {
            Events::trigger('announcements.published', $announcementId, $publisherId);
        } catch (Throwable $exception) {
            $this->logger->error('Announcement {id} was published but its event failed: {exception}', ['id' => $announcementId, 'exception' => $exception]);
        }
    }
}
