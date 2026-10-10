<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;
use DateTimeImmutable;
use DateTimeZone;
use NumberFormatter;

class DashboardCell extends Cell
{
    public array $sections  = [];
    public string $locale   = 'en';
    public string $timezone = 'UTC';

    public function mount(): void
    {
        $numbers = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $zone    = new DateTimeZone($this->timezone);

        foreach ($this->sections as &$section) {
            $section['label'] = lang($section['label'], [], $this->locale);

            foreach ($section['items'] as &$item) {
                $item['title'] = lang($item['title'], [], $this->locale);
                if ($item['type'] === 'metric') {
                    $item['value']       = $numbers->format($item['value'], NumberFormatter::TYPE_INT64);
                    $item['description'] = lang($item['description'], [], $this->locale);
                }
                if ($item['type'] === 'list') {
                    $item['emptyLabel'] = lang($item['emptyLabel'], [], $this->locale);

                    foreach ($item['rows'] as &$row) {
                        if (isset($row['time'])) {
                            $row['displayTime'] = (new DateTimeImmutable($row['time']))->setTimezone($zone)->format('Y-m-d H:i:s');
                        }
                    }
                    unset($row);
                    if (isset($item['moreLink'])) {
                        $item['moreLink']['label'] = lang($item['moreLink']['label'], [], $this->locale);
                    }
                }
            }
            unset($item);
        }
        unset($section);
    }
}
