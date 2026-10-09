<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class SortHeaderCell extends Cell
{
    public string $action           = '';
    public string $field            = '';
    public string $label            = '';
    public string $sort             = '';
    public string $direction        = 'DESC';
    public string $defaultDirection = 'ASC';
    public array $filters           = [];
    public string $ariaSort         = 'none';
    public string $sortClass        = '';
    public string $nextDirection    = 'ASC';

    public function mount(): void
    {
        $active              = $this->sort === $this->field;
        $ascending           = $this->direction === 'ASC';
        $this->ariaSort      = $active ? ($ascending ? 'ascending' : 'descending') : 'none';
        $this->sortClass     = $active ? ($ascending ? ' asc' : ' desc') : '';
        $this->nextDirection = $active ? ($ascending ? 'DESC' : 'ASC') : $this->defaultDirection;
        unset($this->filters['sort'], $this->filters['direction'], $this->filters['page']);
    }
}
