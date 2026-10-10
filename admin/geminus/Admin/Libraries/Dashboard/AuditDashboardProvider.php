<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Models\OperationAuditModel;

class AuditDashboardProvider implements DashboardProvider
{
    public function __construct(private readonly OperationAuditModel $audit)
    {
    }

    public function items(User $viewer): array
    {
        if (! $viewer->can('operation-audit.view')) {
            return [];
        }
        $rows = [];

        foreach ($this->audit->dashboardRecent() as $record) {
            $rows[] = ['title' => $record['action'] . ' ' . $record['target_type'] . ($record['target_id'] === null ? '' : ' #' . $record['target_id']),
                'link'         => ['route' => 'admin/audit', 'query' => ['type' => $record['target_type'], 'target' => $record['target_id'] ?? '']],
                'time'         => Time::parse($record['created_at'], 'UTC')->format('Y-m-d\TH:i:s\Z')];
        }

        return [['id' => 'recent', 'type' => 'list', 'title' => 'Dashboard.auditRecent', 'order' => 10,
            'rows'    => $rows, 'emptyLabel' => 'Dashboard.auditEmpty', 'moreLink' => ['route' => 'admin/audit', 'label' => 'Dashboard.viewAll']]];
    }
}
