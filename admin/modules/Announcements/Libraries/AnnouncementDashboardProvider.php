<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Libraries\Dashboard\DashboardProvider;
use Modules\Announcements\Models\AnnouncementModel;

class AnnouncementDashboardProvider implements DashboardProvider
{
    public function __construct(private readonly AnnouncementModel $announcements)
    {
    }

    public function items(User $viewer): array
    {
        $canManage = $viewer->can('announcements.manage');
        if (! $canManage && ! $viewer->can('announcements.access')) {
            return [];
        }

        $items = [];
        if ($canManage) {
            $items[] = [
                'id'    => 'drafts', 'type' => 'metric', 'title' => 'Announcements.dashboardDrafts', 'order' => 10,
                'value' => $this->announcements->dashboardDraftCount(), 'description' => 'Announcements.dashboardDraftScope',
                'link'  => ['route' => 'admin/announcements', 'query' => ['status' => 'draft']],
            ];
            $items[] = [
                'id'   => 'create', 'type' => 'shortcut', 'title' => 'Announcements.create', 'order' => 20,
                'link' => ['route' => 'admin/announcements/create'], 'icon' => 'plus',
            ];
        }
        $rows = [];

        foreach ($this->announcements->dashboardRecent($canManage) as $announcement) {
            $row  = ['title' => $announcement['title'], 'link' => ['route' => 'admin/announcements/show', 'arguments' => [(int) $announcement['id']]]];
            $time = $announcement[$canManage ? 'created_at' : 'published_at'];
            if ($time !== null) {
                $row['time'] = Time::parse($time, 'UTC')->format('Y-m-d\TH:i:s\Z');
            }
            $rows[] = $row;
        }
        $items[] = [
            'id'       => 'recent', 'type' => 'list', 'title' => $canManage ? 'Announcements.dashboardRecentManaged' : 'Announcements.dashboardRecentPublished',
            'order'    => 30, 'rows' => $rows, 'emptyLabel' => $canManage ? 'Announcements.empty' : 'Announcements.emptyPublished',
            'moreLink' => ['route' => 'admin/announcements', 'label' => 'Dashboard.viewAll'],
        ];

        return $items;
    }
}
