<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class PaginationCell extends Cell
{
    public string $links      = '';
    public int $currentPage   = 1;
    public int $perPage       = 20;
    public string $totalLabel = '';
    public int $total         = 0;
    public int $start         = 0;
    public int $end           = 0;

    public function mount(): void
    {
        $offset      = (max(1, $this->currentPage) - 1) * max(1, $this->perPage);
        $this->start = $this->total > $offset ? $offset + 1 : 0;
        $this->end   = $this->start > 0 ? min($offset + max(1, $this->perPage), $this->total) : 0;
    }
}
