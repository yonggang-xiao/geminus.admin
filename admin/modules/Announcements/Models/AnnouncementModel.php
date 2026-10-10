<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use CodeIgniter\Database\BaseBuilder;
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

    public function dashboardDraftCount(): int
    {
        return $this->visibleTo(true)->where('status', 'draft')->countAllResults();
    }

    public function dashboardRecent(bool $canManage): array
    {
        return $this->visibleTo($canManage)->select('id, title, created_at, published_at')
            ->orderBy($canManage ? 'created_at' : 'published_at', 'DESC')->orderBy('id', 'DESC')->findAll(5);
    }

    public function publishDraft(int $announcementId): bool
    {
        if (! $this->where('status', 'draft')->update($announcementId, ['status' => 'published', 'published_at' => Time::now('UTC')->toDateTimeString()])) {
            throw new RuntimeException('Could not publish announcement.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function visibleTo(bool $canManage): self
    {
        $this->applyVisibility($this->builder(), $canManage);

        return $this;
    }

    public function visibleIds(bool $canManage): BaseBuilder
    {
        return $this->applyVisibility($this->db->table($this->table), $canManage)->select('id');
    }

    private function applyVisibility(BaseBuilder $builder, bool $canManage): BaseBuilder
    {
        if (! $canManage) {
            $builder->where('status', 'published');
        }

        return $builder;
    }
}
