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
    public array $metrics   = [];
    public string $locale   = 'en';
    public string $timezone = 'UTC';

    public function mount(): void
    {
        $this->metrics = [];
        $numbers       = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $zone          = new DateTimeZone($this->timezone);
        $percentages   = new NumberFormatter($this->locale, NumberFormatter::PERCENT);
        $percentages->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        foreach ($this->sections as &$section) {
            $section['label']     = lang($section['label'], [], $this->locale);
            $section['shortcuts'] = [];
            $section['panels']    = [];

            foreach ($section['items'] as &$item) {
                $item['title'] = lang($item['title'], [], $this->locale);
                if ($item['type'] === 'metric') {
                    $item['value']       = $numbers->format($item['value'], NumberFormatter::TYPE_INT64);
                    $item['description'] = lang($item['description'], [], $this->locale);
                    $this->metrics[]     = $item + ['moduleLabel' => $section['label']];
                }
                if ($item['type'] === 'progress') {
                    $item['description'] = lang($item['description'], [], $this->locale);
                    $item['color']       = self::color($item['tone'] ?? 'default');
                    $percentage          = match (true) {
                        $item['max'] === 0              => null,
                        $item['value'] === 0            => 0.0,
                        $item['value'] === $item['max'] => 100.0,
                        default                         => min(99.9, max(0.1, round($item['value'] / $item['max'] * 100, 1))),
                    };
                    $item['percentage']        = $percentage === null ? null : number_format($percentage, 1, '.', '');
                    $item['displayPercentage'] = $item['percentage'] === null ? null : $percentages->format((float) $item['percentage'] / 100);
                    $item['displayCount']      = lang('Dashboard.progressCount', [$numbers->format($item['value'], NumberFormatter::TYPE_INT64), $numbers->format($item['max'], NumberFormatter::TYPE_INT64)], $this->locale);
                    $section['panels'][]       = $item;
                }
                if (in_array($item['type'], ['list', 'status-list'], true)) {
                    $item['emptyLabel'] = lang($item['emptyLabel'], [], $this->locale);

                    foreach ($item['rows'] as &$row) {
                        if ($item['type'] === 'status-list') {
                            $row['status'] = lang($row['status'], [], $this->locale);
                            $row['color']  = self::color($row['tone'] ?? 'default');
                        }
                        if (isset($row['time'])) {
                            $row['displayTime'] = (new DateTimeImmutable($row['time']))->setTimezone($zone)->format('Y-m-d H:i:s');
                        }
                    }
                    unset($row);
                    if (isset($item['moreLink'])) {
                        $item['moreLink']['label'] = lang($item['moreLink']['label'], [], $this->locale);
                    }
                    $section['panels'][] = $item;
                }
                if ($item['type'] === 'shortcut') {
                    $section['shortcuts'][] = $item;
                }
            }
            unset($item);
        }
        unset($section);

        $navigationSections = [];
        $contentSections    = [];

        foreach ($this->sections as $section) {
            if (! $section['unavailable'] && $section['panels'] === [] && $section['shortcuts'] !== []) {
                $navigationSections[] = $section;
            } else {
                $contentSections[] = $section;
            }
        }
        $this->sections = array_merge($navigationSections, $contentSections);
    }

    private static function color(string $tone): string
    {
        return match ($tone) {
            'success' => 'green',
            'warning' => 'yellow',
            'danger'  => 'red',
            'info'    => 'azure',
            default   => 'secondary',
        };
    }
}
