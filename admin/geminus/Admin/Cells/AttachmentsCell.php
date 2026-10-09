<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class AttachmentsCell extends Cell
{
    public string $inputId       = 'attachment-file';
    public string $uploadUrl     = '';
    public string $downloadRoute = '';
    public string $removeRoute   = '';
    public array $routeArguments = [];
    public string $accept        = '';
    public string $hint          = '';
    public string $error         = '';
    public array $labels         = [];
    public array $attachments    = [];
    public array $items          = [];

    public function mount(): void
    {
        $this->items = [];

        foreach ($this->attachments as $attachment) {
            $arguments     = [...$this->routeArguments, $attachment['id']];
            $this->items[] = [
                'name'        => $attachment['original_name'],
                'size'        => number_format($attachment['size_bytes'] / 1024, 1),
                'downloadUrl' => $this->downloadRoute !== '' ? route_to($this->downloadRoute, ...$arguments) : '',
                'removeUrl'   => $this->removeRoute !== '' ? route_to($this->removeRoute, ...$arguments) : '',
            ];
        }
    }
}
