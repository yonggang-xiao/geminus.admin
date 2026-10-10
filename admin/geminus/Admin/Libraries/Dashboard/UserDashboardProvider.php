<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Models\UserModel;

class UserDashboardProvider implements DashboardProvider
{
    public function __construct(private readonly UserModel $users)
    {
    }

    public function items(User $viewer): array
    {
        $items = [];
        if ($viewer->can('users.view')) {
            $items[] = ['id' => 'total', 'type' => 'metric', 'title' => 'Dashboard.usersTotal', 'order' => 10,
                'value'      => $this->users->dashboardCount(), 'description' => 'Dashboard.usersScope', 'link' => ['route' => 'admin/users']];
            $items[] = ['id' => 'banned', 'type' => 'metric', 'title' => 'Dashboard.usersBanned', 'order' => 20,
                'value'      => $this->users->dashboardCount(true), 'description' => 'Dashboard.usersBannedScope'];
            $rows = [];

            foreach ($this->users->dashboardRecent() as $user) {
                $banned = $user['status'] === 'banned';
                $row    = ['title' => $user['username'], 'link' => ['route' => 'admin/users', 'query' => ['q' => $user['username']]],
                    'status'       => $banned ? 'Dashboard.userBanned' : 'Dashboard.userNormal',
                    'tone'         => $banned ? 'danger' : 'success', 'icon' => $banned ? 'ban' : 'check'];
                if ($user['created_at'] !== null) {
                    $row['time'] = Time::parse($user['created_at'], 'UTC')->format('Y-m-d\TH:i:s\Z');
                }
                $rows[] = $row;
            }
            $items[] = ['id' => 'recent', 'type' => 'status-list', 'title' => 'Dashboard.usersRecent', 'order' => 40, 'icon' => 'users',
                'rows'       => $rows, 'emptyLabel' => 'Dashboard.usersEmpty', 'moreLink' => ['route' => 'admin/users', 'label' => 'Dashboard.viewAll']];
        }
        if ($viewer->can('users.create')) {
            $items[] = ['id' => 'create', 'type' => 'shortcut', 'title' => 'Admin.createUser', 'order' => 30,
                'link'       => ['route' => 'admin/users/create'], 'icon' => 'user-plus'];
        }

        return $items;
    }
}
