<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use RuntimeException;

class AnnouncementModel extends Model
{
    protected $table         = 'example_announcements';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['title', 'body', 'status', 'published_at'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function publishDraft(int $announcementId): bool
    {
        if (! $this->where('status', 'draft')->update($announcementId, ['status' => 'published', 'published_at' => Time::now('UTC')->toDateTimeString()])) {
            throw new RuntimeException('Could not publish announcement.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function visibleTo(bool $canManage): self
    {
        if (! $canManage) {
            $this->where('status', 'published');
        }

        return $this;
    }
}
