<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Database\BaseConnection;
use Geminus\Admin\Libraries\DataManagement\UploadStorage;
use Geminus\Admin\Models\AttachmentModel;
use Geminus\Admin\Models\NotificationModel;
use Modules\Announcements\Models\AnnouncementModel;
use RuntimeException;
use Throwable;

final class AnnouncementDeletion
{
    public function __construct(private readonly BaseConnection $db, private readonly UploadStorage $storage)
    {
    }

    public function delete(int $announcementId): bool
    {
        $announcements = new AnnouncementModel($this->db);
        if ($announcements->find($announcementId) === null) {
            return false;
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not start announcement deletion.');
        }

        try {
            $attachments = new AttachmentModel($this->db);
            $files       = $attachments->forResource('announcement', $announcementId)->findAll();
            if (! $attachments->forResource('announcement', $announcementId)->delete()
                || ! (new NotificationModel($this->db))->where('source', 'announcements.published')->where('reference', $announcementId)->delete()
                || ! $announcements->delete($announcementId)
                || ! $this->db->transStatus()) {
                throw new RuntimeException('Could not delete announcement.');
            }
            if (! $this->db->transCommit()) {
                throw new RuntimeException('Could not commit announcement deletion.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }

        foreach ($files as $file) {
            try {
                $this->storage->delete($file['filename']);
            } catch (Throwable $exception) {
                log_message('error', 'Announcement attachment cleanup failed: {type}', ['type' => $exception::class]);
            }
        }

        return true;
    }
}
