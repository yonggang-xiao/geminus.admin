<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Models\EmailDeliveryLogModel;

class EmailDashboardProvider implements DashboardProvider
{
    public function __construct(private readonly EmailDeliveryLogModel $deliveries)
    {
    }

    public function items(User $viewer): array
    {
        if (! $viewer->can('email-deliveries.view')) {
            return [];
        }

        $counts = $this->deliveries->dashboardCounts();

        return [
            ['id'       => 'queued', 'type' => 'metric', 'title' => 'Dashboard.emailQueued', 'order' => 10,
                'value' => $counts['queued'], 'description' => 'Dashboard.emailQueuedScope',
                'link'  => ['route' => 'admin/mail/deliveries', 'query' => ['view' => 'logs', 'status' => 'queued']]],
            ['id'       => 'failed', 'type' => 'metric', 'title' => 'Dashboard.emailFailed', 'order' => 20,
                'value' => $counts['failed'], 'description' => 'Dashboard.emailFailedScope',
                'link'  => ['route' => 'admin/mail/deliveries', 'query' => ['view' => 'logs', 'status' => 'failed']]],
            ['id'       => 'success-rate', 'type' => 'progress', 'title' => 'Dashboard.emailSuccessRate', 'order' => 30,
                'value' => $counts['sent'], 'max' => $counts['total'], 'description' => 'Dashboard.emailSuccessScope', 'tone' => 'success', 'icon' => 'mail-check',
                'link'  => ['route' => 'admin/mail/deliveries', 'query' => ['view' => 'logs', 'status' => 'sent']]],
        ];
    }
}
