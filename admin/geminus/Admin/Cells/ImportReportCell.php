<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class ImportReportCell extends Cell
{
    public string $title       = '';
    public string $rowLabel    = '';
    public string $resultLabel = '';
    public string $reasonLabel = '';
    public array $columns      = [];
    public array $rows         = [];
    public array $resultLabels = [];
    public array $reasonLabels = [];
    public array $counts       = [];

    public function mount(): void
    {
        $this->counts = array_fill_keys(array_keys($this->resultLabels), 0);

        foreach ($this->rows as $row) {
            $result                = $row['result'];
            $this->counts[$result] = ($this->counts[$result] ?? 0) + 1;
        }
    }
}
