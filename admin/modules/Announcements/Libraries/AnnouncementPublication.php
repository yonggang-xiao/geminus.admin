<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use Modules\Announcements\Models\AnnouncementModel;
use Psr\Log\LoggerInterface;
use Throwable;

class AnnouncementPublication
{
    public function __construct(private readonly AnnouncementModel $model, private readonly LoggerInterface $logger)
    {
    }

    public function publish(int $announcementId, int $publisherId, bool $notify = true, ?Time $publishedAt = null): bool
    {
        if (! $this->model->publishDraft($announcementId, $publishedAt)) {
            return false;
        }

        if ($notify) {
            $this->notifyPublished($announcementId, $publisherId);
        }

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
