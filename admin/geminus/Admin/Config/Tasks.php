<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
use CodeIgniter\Tasks\Scheduler;

class Tasks extends BaseTasks
{
    public function init(Scheduler $schedule): void
    {
        $schedule->command('queue:work email --stop-when-empty')->everyMinute()->named('send-email');
    }
}
