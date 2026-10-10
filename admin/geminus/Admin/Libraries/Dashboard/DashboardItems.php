<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use DateTimeImmutable;
use InvalidArgumentException;

class DashboardItems
{
    public function __construct(private readonly DashboardLinks $links)
    {
    }

    public function validate(array $items, string $provider, string $locale): array
    {
        if (! array_is_list($items)) {
            throw new InvalidArgumentException('Dashboard items must be a list.');
        }
        $identifiers = [];

        foreach ($items as &$item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Dashboard item must be an array.');
            }
            $required = match ($item['type'] ?? null) {
                'metric'      => ['value', 'description'],
                'list'        => ['rows', 'emptyLabel'],
                'progress'    => ['value', 'max', 'description'],
                'status-list' => ['rows', 'emptyLabel'],
                'shortcut'    => ['link'],
                default       => throw new InvalidArgumentException('Unsupported dashboard item type.'),
            };
            $optional = match ($item['type']) {
                'metric'      => ['link'],
                'list'        => ['moreLink'],
                'progress'    => ['link', 'tone', 'icon'],
                'status-list' => ['moreLink', 'icon'],
                'shortcut'    => ['icon'],
            };
            self::fields($item, ['id', 'type', 'title', 'order', ...$required], $optional);
            self::identifier($item['id']);
            self::languageKey($item['title']);
            if (! is_int($item['order']) || isset($identifiers[$item['id']])) {
                throw new InvalidArgumentException('Dashboard item order or duplicate identifier is invalid.');
            }
            $identifiers[$item['id']] = true;

            if (in_array($item['type'], ['metric', 'progress'], true)) {
                if (! is_int($item['value']) || $item['value'] < 0) {
                    throw new InvalidArgumentException('Dashboard metrics require a nonnegative integer.');
                }
                self::languageKey($item['description']);
            }
            if ($item['type'] === 'progress') {
                if (! is_int($item['max']) || $item['max'] < 0 || $item['value'] > $item['max']) {
                    throw new InvalidArgumentException('Dashboard progress requires a nonnegative total and value within total.');
                }
                if (array_key_exists('tone', $item)) {
                    self::tone($item['tone']);
                }
            }
            if (in_array($item['type'], ['list', 'status-list'], true)) {
                self::languageKey($item['emptyLabel']);
                if (! is_array($item['rows']) || ! array_is_list($item['rows']) || count($item['rows']) > 5) {
                    throw new InvalidArgumentException('Dashboard lists allow at most five rows.');
                }

                foreach ($item['rows'] as &$row) {
                    if (! is_array($row)) {
                        throw new InvalidArgumentException('Invalid dashboard row.');
                    }
                    self::fields($row, ['title', 'link', ...($item['type'] === 'status-list' ? ['status'] : [])], $item['type'] === 'status-list' ? ['time', 'tone', 'icon'] : ['time']);
                    if (! is_string($row['title'])) {
                        throw new InvalidArgumentException('Dashboard row titles must be text.');
                    }
                    if ($item['type'] === 'status-list') {
                        self::languageKey($row['status']);
                        if (array_key_exists('tone', $row)) {
                            self::tone($row['tone']);
                        }
                        if (array_key_exists('icon', $row)) {
                            self::icon($row['icon']);
                        }
                    }
                    if (array_key_exists('time', $row)) {
                        self::utcTime($row['time']);
                    }
                    $row['link'] = $this->resolve($row['link'], $locale);
                }
                unset($row);
            }
            if (array_key_exists('icon', $item)) {
                self::icon($item['icon']);
            }

            foreach (['link', 'moreLink'] as $field) {
                if (array_key_exists($field, $item)) {
                    $item[$field] = $this->resolve($item[$field], $locale, $field === 'moreLink');
                }
            }
            $item['key'] = $provider . ':' . $item['id'];
        }
        unset($item);
        usort($items, static fn (array $left, array $right): int => ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']));

        return $items;
    }

    public static function fields(array $value, array $required, array $optional = []): void
    {
        if (array_diff($required, array_keys($value)) !== [] || array_diff(array_keys($value), [...$required, ...$optional]) !== []) {
            throw new InvalidArgumentException('Invalid dashboard contract fields.');
        }
    }

    public static function identifier(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-z0-9-]{1,64}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid dashboard identifier.');
        }
    }

    public static function languageKey(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[A-Za-z][A-Za-z0-9_]*\.[A-Za-z][A-Za-z0-9_.]*\z/', $value) !== 1) {
            throw new InvalidArgumentException('Dashboard labels must be language keys.');
        }
    }

    private static function tone(mixed $value): void
    {
        if (! is_string($value) || ! in_array($value, ['default', 'success', 'warning', 'danger', 'info'], true)) {
            throw new InvalidArgumentException('Invalid dashboard semantic tone.');
        }
    }

    private static function icon(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid dashboard icon name.');
        }
    }

    private static function utcTime(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)\z/', $value) !== 1) {
            throw new InvalidArgumentException('Dashboard dates require UTC ISO 8601.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        if ($date === false || $date->format('Y-m-d\TH:i:s') !== substr($value, 0, 19)) {
            throw new InvalidArgumentException('Invalid dashboard date.');
        }
    }

    private function resolve(mixed $link, string $locale, bool $more = false): array
    {
        if (! is_array($link)) {
            throw new InvalidArgumentException('Dashboard links must be structured arrays.');
        }

        return $this->links->resolve($link, $locale, $more);
    }
}
