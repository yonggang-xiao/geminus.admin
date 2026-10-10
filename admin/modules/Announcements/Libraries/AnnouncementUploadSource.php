<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Libraries\DataManagement\UploadSource;
use Modules\Announcements\Models\AnnouncementModel;

class AnnouncementUploadSource implements UploadSource
{
    public function __construct(private readonly AnnouncementModel $announcements)
    {
    }

    public function visibleResources(User $viewer): ?BaseBuilder
    {
        $canManage = $viewer->can('announcements.manage');
        if (! $canManage && ! $viewer->can('announcements.access')) {
            return null;
        }

        return $this->announcements->visibleIds($canManage);
    }

    public function describe(array $resourceIds): array
    {
        $descriptions = [];

        foreach ($this->announcements->select('id, title')->whereIn('id', $resourceIds)->findAll() as $announcement) {
            $descriptions[$announcement['id']] = [
                'label'             => 'Announcements.title',
                'title'             => $announcement['title'],
                'recordRoute'       => 'admin/announcements/show',
                'recordArguments'   => [$announcement['id']],
                'downloadRoute'     => 'admin/announcements/attachments/download',
                'previewRoute'      => 'admin/announcements/attachments/preview',
                'downloadArguments' => [$announcement['id']],
            ];
        }

        return $descriptions;
    }
}
