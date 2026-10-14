<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Validation\ValidationInterface;
use Modules\Announcements\Models\AnnouncementModel;
use RuntimeException;
use Throwable;

class AnnouncementApiPublication
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly AnnouncementModel $announcements,
        private readonly AnnouncementPublication $publication,
        private readonly ValidationInterface $validation,
    ) {
    }

    public function publish(int $actorId, array $input): array
    {
        $data = AnnouncementContent::apiInput($input, $this->validation);
        if ($this->db->transDepth !== 0) {
            throw new RuntimeException('API publication must own the outer transaction.');
        }
        $this->db->resetTransStatus();
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not begin publication.');
        }

        try {
            $publishedAt    = Time::now('UTC');
            $announcementId = $this->announcements->insert(['title' => $data['title'], 'body' => $data['body'], 'status' => 'published', 'published_at' => $publishedAt->toDateTimeString()]);
            if ($announcementId === false) {
                throw new RuntimeException('Could not create announcement.');
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Could not commit publication.');
            }
        } catch (Throwable $exception) {
            if ($this->db->transDepth > 0) {
                $this->db->transRollback();
            }
            $this->db->resetTransStatus();

            throw $exception;
        }
        $this->publication->notifyPublished((int) $announcementId, $actorId);

        return [
            'data' => ['id' => (int) $announcementId, 'status' => 'published', 'published_at' => $publishedAt->format('Y-m-d\TH:i:s\Z')],
        ];
    }
}
