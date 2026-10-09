<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use CodeIgniter\Model;

class AnnouncementModel extends Model
{
    protected $table         = 'example_announcements';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['title', 'body', 'status', 'published_at'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function visibleTo(bool $canManage): self
    {
        if (! $canManage) {
            $this->where('status', 'published');
        }

        return $this;
    }
}
