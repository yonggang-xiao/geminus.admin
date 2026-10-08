<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;

class AdminMenu extends BaseConfig
{
    /**
     * @var list<array{permission: string, route: string, label: string, icon: string, active: string}>
     */
    public array $items = [];
}
