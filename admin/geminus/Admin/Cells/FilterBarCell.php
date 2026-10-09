<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;
use InvalidArgumentException;

class FilterBarCell extends Cell
{
    public string $action      = '';
    public string $clearUrl    = '';
    public string $submitLabel = '';
    public string $clearLabel  = '';
    public array $fields       = [];
    public array $hidden       = [];

    public function mount(): void
    {
        foreach ($this->fields as $field) {
            if (! in_array($field['type'] ?? 'text', ['text', 'select', 'date', 'number'], true)) {
                throw new InvalidArgumentException('Unsupported filter field type.');
            }
        }
    }
}
