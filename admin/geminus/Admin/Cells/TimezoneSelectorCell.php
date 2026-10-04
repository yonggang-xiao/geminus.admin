<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;

class TimezoneSelectorCell extends Cell
{
    public const TIMEZONES = [
        'UTC',
        'Asia/Hong_Kong',
        'Asia/Shanghai',
        'Asia/Tokyo',
        'Australia/Sydney',
        'America/New_York',
        'Europe/London',
        'Europe/Berlin',
    ];

    public array $timezones         = [];
    public string $selectedTimezone = 'UTC';
    public string $inputId          = 'timezone';
    public bool $invalid            = false;

    public function mount(): void
    {
        if (empty($this->timezones)) {
            $this->timezones = self::TIMEZONES;
        }
    }
}
