<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class DateFieldCell extends Cell
{
    public string $inputId     = '';
    public string $name        = '';
    public string $label       = '';
    public string $value       = '';
    public string $error       = '';
    public string $hint        = '';
    public string $min         = '';
    public string $max         = '';
    public bool $required      = false;
    public string $describedBy = '';

    public function mount(): void
    {
        $this->describedBy = trim(($this->hint !== '' ? $this->inputId . '-hint' : '') . ' ' . ($this->error !== '' ? $this->inputId . '-error' : ''));
    }
}
