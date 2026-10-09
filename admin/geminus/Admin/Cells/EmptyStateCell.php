<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class EmptyStateCell extends Cell
{
    public string $message     = '';
    public bool $filtered      = false;
    public string $createUrl   = '';
    public string $createLabel = '';
    public string $createIcon  = 'plus';
    public string $clearUrl    = '';
    public string $clearLabel  = '';
    public string $actionUrl   = '';
    public string $actionLabel = '';
    public string $actionIcon  = '';

    public function mount(): void
    {
        $this->actionUrl   = $this->filtered ? $this->clearUrl : $this->createUrl;
        $this->actionLabel = $this->filtered ? $this->clearLabel : $this->createLabel;
        $this->actionIcon  = $this->filtered ? 'x' : $this->createIcon;
    }
}
