<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use CodeIgniter\Database\RawSql;
use CodeIgniter\Model;
use DateTimeImmutable;
use InvalidArgumentException;

final class ListQuery
{
    public readonly string $search;
    public readonly string $sort;
    public readonly string $direction;

    /**
     * @param array<string, array{field: string, escape: bool}|string> $sortFields
     */
    public function __construct(private readonly array $input, private readonly array $sortFields, string $defaultSort, public readonly int $perPage = 20)
    {
        if (! isset($sortFields[$defaultSort]) || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid list query configuration.');
        }

        $this->search    = mb_substr(trim($this->value('q')), 0, 100);
        $requestedSort   = $this->value('sort');
        $this->sort      = isset($sortFields[$requestedSort]) ? $requestedSort : $defaultSort;
        $this->direction = strtoupper($this->value('direction')) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * @param list<string>                                              $searchFields
     * @param array<string, array{field: string, values: list<string>}> $filters
     */
    public function apply(Model $model, array $searchFields = [], array $filters = [], string $stableField = 'id', array $ranges = []): Model
    {
        if ($this->search !== '' && $searchFields !== []) {
            $model->groupStart();

            foreach ($searchFields as $index => $field) {
                if ($index === 0) {
                    $model->like($field, $this->search, 'both', null, true);
                } else {
                    $model->orLike($field, $this->search, 'both', null, true);
                }
            }
            $model->groupEnd();
        }

        foreach ($filters as $name => $definition) {
            $value = $this->value($name);
            if ($value !== '' && in_array($value, $definition['values'], true)) {
                $model->where($definition['field'], $value);
            }
        }

        foreach ($ranges as $name => $definition) {
            $range = $this->range($name, $definition['type']);
            if ($range['from'] !== '') {
                $model->where($definition['field'] . ' >=', $definition['type'] === 'number' ? new RawSql($range['from']) : $range['from']);
            }
            if ($range['to'] !== '') {
                if ($definition['type'] === 'date') {
                    $model->where($definition['field'] . ' <', (new DateTimeImmutable($range['to']))->modify('+1 day')->format('Y-m-d'));
                } else {
                    $model->where($definition['field'] . ' <=', new RawSql($range['to']));
                }
            }
        }

        $sortField = $this->sortFields[$this->sort];
        if (is_array($sortField)) {
            $model->orderBy($sortField['field'], $this->direction, $sortField['escape']);
        } else {
            $model->orderBy($sortField, $this->direction);
        }

        return $model->orderBy($stableField, 'DESC');
    }

    public function range(string $name, string $type): array
    {
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $name) || ! in_array($type, ['date', 'number'], true)) {
            throw new InvalidArgumentException('Invalid range configuration.');
        }
        $range = ['from' => '', 'to' => ''];

        foreach (array_keys($range) as $bound) {
            $value = $this->value($name . '_' . $bound);
            if ($type === 'date') {
                if (! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)) {
                    continue;
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($date !== false && $date->format('Y-m-d') === $value && $value >= '0001-01-01' && $value < '9999-12-31') {
                    $range[$bound] = $value;
                }
            } elseif (strlen($value) <= 30 && preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', $value)) {
                $range[$bound] = $value;
            }
        }
        if ($range['from'] !== '' && $range['to'] !== '' && ($type === 'date' ? $range['from'] > $range['to'] : $this->compareNumbers($range['from'], $range['to']) > 0)) {
            return ['from' => '', 'to' => ''];
        }

        return $range;
    }

    private function compareNumbers(string $from, string $to): int
    {
        $numbers = [];

        foreach ([$from, $to] as $value) {
            [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
            $whole              = ltrim($whole, '0');
            $whole              = $whole === '' ? '0' : $whole;
            $fraction           = rtrim($fraction, '0');
            $numbers[]          = ['whole' => $whole, 'fraction' => $fraction, 'negative' => str_starts_with($value, '-') && ($whole !== '0' || $fraction !== '')];
        }
        [$lower, $upper] = $numbers;
        if ($lower['negative'] !== $upper['negative']) {
            return $lower['negative'] ? -1 : 1;
        }
        $comparison = strlen($lower['whole']) <=> strlen($upper['whole']);
        if ($comparison === 0) {
            $comparison = strcmp($lower['whole'], $upper['whole']);
        }
        if ($comparison === 0) {
            $length     = max(strlen($lower['fraction']), strlen($upper['fraction']));
            $comparison = strcmp(str_pad($lower['fraction'], $length, '0'), str_pad($upper['fraction'], $length, '0'));
        }

        return $lower['negative'] ? -$comparison : $comparison;
    }

    private function value(string $name): string
    {
        return is_string($this->input[$name] ?? null) ? $this->input[$name] : '';
    }
}
