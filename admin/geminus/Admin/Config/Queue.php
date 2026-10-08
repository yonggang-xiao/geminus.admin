<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Queue\Config\Queue as BaseQueue;
use Geminus\Admin\Jobs\SendEmail;

class Queue extends BaseQueue
{
    public array $jobHandlers = [
        'send-email' => SendEmail::class,
    ];
}
