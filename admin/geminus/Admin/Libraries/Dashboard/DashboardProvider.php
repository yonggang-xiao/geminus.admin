<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\Shield\Entities\User;

interface DashboardProvider
{
    public function items(User $viewer): array;
}
